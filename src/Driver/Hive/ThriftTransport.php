<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive;

use Erikwang2013\BigData\Driver\Hive\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Hive\Exception\RequestException;
use Erikwang2013\BigData\Driver\Hive\Thrift\{
    TCLIServiceClient,
    TCloseSessionReq,
    TColumn,
    TColumnValue,
    TExecuteStatementReq,
    TFetchOrientation,
    TFetchResultsReq,
    TGetResultSetMetadataReq,
    TOperationHandle,
    TOpenSessionReq,
    TProtocolVersion,
    TRow,
    TRowSet,
    TSessionHandle,
    TStatus,
    TStatusCode,
    TTableSchema,
    TTypeEntry,
    TTypeId,
};
use Thrift\Exception\TApplicationException;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TSocket;
use Thrift\Transport\TTransport;

final class ThriftTransport implements HiveTransport
{
    private ?TCLIServiceClient $client = null;

    private ?TTransport $transport = null;

    private ?TSessionHandle $session = null;

    private ?TOperationHandle $operation = null;

    private bool $opened = false;

    private bool $hasResultSet = false;

    /** @var list<array{name: string, type: string}> */
    private array $columns = [];

    public function __construct(
        private HiveConfig $config,
        private ?TCLIServiceClient $injectedClient = null,
    ) {
    }

    public function open(): void
    {
        if ($this->opened) {
            return;
        }

        try {
            $response = $this->client()->OpenSession(new TOpenSessionReq([
                'client_protocol' => TProtocolVersion::HIVE_CLI_SERVICE_PROTOCOL_V10,
                'username' => $this->config->username,
                'password' => $this->config->password,
                'configuration' => [],
            ]));

            if (!$this->isSuccess($response->status) || $response->sessionHandle === null) {
                throw new ConnectionException('HiveServer2 OpenSession failed: ' . $this->statusMessage($response->status));
            }

            $this->session = $response->sessionHandle;
            $this->opened = true;

            if ($this->config->database !== '' && $this->config->database !== 'default') {
                $this->runStatement('USE `' . str_replace('`', '``', $this->config->database) . '`');
            }
        } catch (\Throwable $e) {
            $this->close();

            if ($e instanceof ConnectionException) {
                throw $e;
            }

            throw new ConnectionException(sprintf(
                'Unable to connect to HiveServer2 at %s:%d: %s',
                $this->config->host,
                $this->config->port,
                $e->getMessage(),
            ), 0, $e);
        }
    }

    public function execute(string $sql): array
    {
        $this->open();

        $response = $this->rpc(fn () => $this->client()->ExecuteStatement(new TExecuteStatementReq([
            'sessionHandle' => $this->session,
            'statement' => $sql,
            'confOverlay' => [],
            'runAsync' => false,
        ])));
        $this->checkStatus($response->status, 'ExecuteStatement');

        $this->operation = $response->operationHandle;
        $this->columns = [];
        $this->hasResultSet = false;

        if ($this->operation !== null && $this->operation->hasResultSet) {
            $metadata = $this->rpc(fn () => $this->client()->GetResultSetMetadata(new TGetResultSetMetadataReq([
                'operationHandle' => $this->operation,
            ])));
            $this->checkStatus($metadata->status, 'GetResultSetMetadata');
            $this->columns = $this->mapColumns($metadata->schema);
            $this->hasResultSet = true;
        }

        return ['columns' => $this->columns, 'handle' => $this->operation];
    }

    public function fetch(int $maxRows): array
    {
        if (!$this->hasResultSet || $this->operation === null) {
            return ['rows' => [], 'hasMore' => false];
        }

        $response = $this->rpc(fn () => $this->client()->FetchResults(new TFetchResultsReq([
            'operationHandle' => $this->operation,
            'orientation' => TFetchOrientation::FETCH_NEXT,
            'maxRows' => $maxRows,
        ])));
        $this->checkStatus($response->status, 'FetchResults');

        return [
            'rows' => $this->decodeRowSet($response->results),
            'hasMore' => (bool) $response->hasMoreRows,
        ];
    }

    public function close(): void
    {
        if ($this->opened && $this->session !== null && $this->client !== null) {
            try {
                $this->client->CloseSession(new TCloseSessionReq(['sessionHandle' => $this->session]));
            } catch (\Throwable) {
                // Best effort: the server may already have dropped the connection.
            }
        }

        $this->reset();
    }

    /**
     * @template T
     * @param callable(): T $call
     * @return T
     */
    private function rpc(callable $call): mixed
    {
        try {
            return $call();
        } catch (RequestException $e) {
            throw $e;
        } catch (TTransportException $e) {
            $this->reset();
            throw new ConnectionException('HiveServer2 RPC failed: ' . $e->getMessage(), 0, $e);
        } catch (TApplicationException $e) {
            $this->reset();
            throw new RequestException($e->getMessage(), null, null, $e);
        } catch (\Throwable $e) {
            $this->reset();
            throw new ConnectionException('HiveServer2 RPC failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function reset(): void
    {
        try {
            $this->transport?->close();
        } catch (\Throwable) {
            // Best effort: the peer may already have gone away.
        }

        $this->transport = null;
        $this->client = null;
        $this->session = null;
        $this->operation = null;
        $this->opened = false;
        $this->hasResultSet = false;
        $this->columns = [];
    }

    private function client(): TCLIServiceClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if ($this->injectedClient !== null) {
            return $this->client = $this->injectedClient;
        }

        $this->transport = $this->createTransport();
        $this->transport->open();

        return $this->client = new TCLIServiceClient(new TBinaryProtocol($this->transport));
    }

    private function createTransport(): TTransport
    {
        if ($this->config->transport === 'http') {
            // THttpClient only uses the stream context for https; verifySsl is
            // honoured there and plain http needs no SSL options.
            $http = new THttpClient(
                $this->config->host,
                $this->config->port,
                $this->config->httpPath,
                $this->config->scheme,
                $this->config->scheme === 'https' && !$this->config->verifySsl
                    ? ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]
                    : [],
            );
            $http->setTimeoutSecs($this->config->timeout);

            if ($this->config->auth !== 'NONE') {
                $http->addHeaders([
                    'Authorization' => 'Basic ' . base64_encode(
                        $this->config->username . ':' . ($this->config->password ?? ''),
                    ),
                ]);
            }

            return $http;
        }

        $socket = new TSocket($this->config->host, $this->config->port);
        $timeout = (int) ceil($this->config->timeout);
        $socket->setConnectTimeout($timeout);
        $socket->setSendTimeout($timeout);
        $socket->setRecvTimeout($timeout);

        $transport = new TBufferedTransport($socket);

        if ($this->config->auth !== 'NONE') {
            return new SaslPlainTransport(
                $transport,
                $this->config->username,
                $this->config->password ?? '',
            );
        }

        return $transport;
    }

    private function runStatement(string $sql): void
    {
        $response = $this->client()->ExecuteStatement(new TExecuteStatementReq([
            'sessionHandle' => $this->session,
            'statement' => $sql,
            'confOverlay' => [],
            'runAsync' => false,
        ]));
        $this->checkStatus($response->status, 'ExecuteStatement');
    }

    private function checkStatus(?TStatus $status, string $operation): void
    {
        if ($this->isSuccess($status)) {
            return;
        }

        throw new RequestException(
            $operation . ' failed: ' . $this->statusMessage($status),
            $status?->sqlState,
            $status?->errorCode,
        );
    }

    private function isSuccess(?TStatus $status): bool
    {
        return $status !== null && (
            $status->statusCode === TStatusCode::SUCCESS_STATUS
            || $status->statusCode === TStatusCode::SUCCESS_WITH_INFO_STATUS
        );
    }

    private function statusMessage(?TStatus $status): string
    {
        if ($status === null) {
            return 'no status returned';
        }

        if ($status->errorMessage !== null && $status->errorMessage !== '') {
            return $status->errorMessage;
        }

        return TStatusCode::$__names[$status->statusCode] ?? ('status ' . $status->statusCode);
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    private function mapColumns(?TTableSchema $schema): array
    {
        $columns = [];
        foreach ($schema->columns ?? [] as $column) {
            $columns[] = [
                'name' => (string) $column->columnName,
                'type' => $this->typeName($column->typeDesc->types[0] ?? null),
            ];
        }

        return $columns;
    }

    private function typeName(?TTypeEntry $entry): string
    {
        if ($entry === null) {
            return 'unknown';
        }

        if ($entry->primitiveEntry !== null) {
            $name = TTypeId::$__names[$entry->primitiveEntry->type] ?? null;

            return $name === null ? 'unknown' : strtolower(substr($name, 0, -strlen('_TYPE')));
        }

        return match (true) {
            $entry->arrayEntry !== null => 'array',
            $entry->mapEntry !== null => 'map',
            $entry->structEntry !== null => 'struct',
            $entry->unionEntry !== null => 'union',
            $entry->userDefinedTypeEntry !== null => 'user_defined',
            default => 'unknown',
        };
    }

    /**
     * @return list<list<mixed>>
     */
    private function decodeRowSet(?TRowSet $rowSet): array
    {
        if ($rowSet === null) {
            return [];
        }

        if (!empty($rowSet->columns)) {
            return $this->decodeColumns($rowSet->columns);
        }

        $rows = [];
        foreach ($rowSet->rows ?? [] as $row) {
            $rows[] = $this->decodeRow($row);
        }

        return $rows;
    }

    /**
     * @param list<TColumn> $columns
     * @return list<list<mixed>>
     */
    private function decodeColumns(array $columns): array
    {
        $values = [];
        $nulls = [];
        $rowCount = 0;

        foreach ($columns as $index => $column) {
            $batch = $column->boolVal ?? $column->byteVal ?? $column->i16Val
                ?? $column->i32Val ?? $column->i64Val ?? $column->doubleVal
                ?? $column->stringVal ?? $column->binaryVal;

            $values[$index] = $batch?->values ?? [];
            $nulls[$index] = $batch?->nulls ?? '';
            $rowCount = max($rowCount, count($values[$index]));
        }

        $rows = [];
        for ($row = 0; $row < $rowCount; ++$row) {
            $decoded = [];
            foreach ($columns as $index => $column) {
                $decoded[] = $this->isNull($nulls[$index], $row) ? null : ($values[$index][$row] ?? null);
            }
            $rows[] = $decoded;
        }

        return $rows;
    }

    private function isNull(string $nulls, int $row): bool
    {
        $byte = $row >> 3;
        if (!isset($nulls[$byte])) {
            return false;
        }

        return (ord($nulls[$byte]) & (1 << ($row & 7))) !== 0;
    }

    /**
     * @return list<mixed>
     */
    private function decodeRow(TRow $row): array
    {
        $values = [];
        foreach ($row->colVals ?? [] as $columnValue) {
            $values[] = $this->decodeValue($columnValue);
        }

        return $values;
    }

    private function decodeValue(TColumnValue $columnValue): mixed
    {
        return $columnValue->boolVal?->value
            ?? $columnValue->byteVal?->value
            ?? $columnValue->i16Val?->value
            ?? $columnValue->i32Val?->value
            ?? $columnValue->i64Val?->value
            ?? $columnValue->doubleVal?->value
            ?? $columnValue->stringVal?->value;
    }
}
