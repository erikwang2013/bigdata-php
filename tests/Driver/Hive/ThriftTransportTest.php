<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hive;

use Erikwang2013\BigData\Driver\Hive\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Hive\Exception\RequestException;
use Erikwang2013\BigData\Driver\Hive\HiveConfig;
use Erikwang2013\BigData\Driver\Hive\ThriftTransport;
use Erikwang2013\BigData\Driver\Hive\Thrift\{
    TCLIServiceClient,
    TCloseOperationReq,
    TCloseOperationResp,
    TCloseSessionReq,
    TCloseSessionResp,
    TColumn,
    TColumnDesc,
    TColumnValue,
    TExecuteStatementReq,
    TExecuteStatementResp,
    TFetchOrientation,
    TFetchResultsReq,
    TFetchResultsResp,
    TGetResultSetMetadataReq,
    TGetResultSetMetadataResp,
    THandleIdentifier,
    TI32Column,
    TI32Value,
    TOpenSessionReq,
    TOpenSessionResp,
    TOperationHandle,
    TOperationType,
    TPrimitiveTypeEntry,
    TProtocolVersion,
    TRow,
    TRowSet,
    TSessionHandle,
    TStatus,
    TStatusCode,
    TStringColumn,
    TStringValue,
    TTableSchema,
    TTypeDesc,
    TTypeEntry,
    TTypeId,
};
use PHPUnit\Framework\TestCase;
use Thrift\Exception\TApplicationException;
use Thrift\Exception\TTransportException;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TSocket;
use Thrift\Transport\TSSLSocket;

final class ThriftTransportTest extends TestCase
{
    public function test_open_session_uses_configured_credentials(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $config = HiveConfig::fromArray([
            'host' => 'hive.local',
            'auth' => 'PLAIN',
            'username' => 'alice',
            'password' => 'secret',
        ]);

        (new ThriftTransport($config, $client))->open();

        $request = $client->requests['OpenSession'][0];
        self::assertInstanceOf(TOpenSessionReq::class, $request);
        self::assertSame(TProtocolVersion::HIVE_CLI_SERVICE_PROTOCOL_V10, $request->client_protocol);
        self::assertSame('alice', $request->username);
        self::assertSame('secret', $request->password);
    }

    public function test_execute_maps_metadata_columns(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = self::executeStatementResponse();
        $client->getResultSetMetadataResponse = self::metadataResponse();
        $client->fetchResponses = [self::emptyFetchResponse()];

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $result = $transport->execute('SELECT id, name FROM users');

        self::assertSame([
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
        ], $result['columns']);
        self::assertInstanceOf(TOperationHandle::class, $result['handle']);
    }

    public function test_fetch_maps_columnar_results_and_preserves_nulls(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = self::executeStatementResponse();
        $client->getResultSetMetadataResponse = self::metadataResponse();
        $client->fetchResponses = [self::columnarFetchResponse()];

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $transport->execute('SELECT id, name FROM users');
        $batch = $transport->fetch(1000);

        self::assertSame([[1, 'a'], [null, 'b'], [3, null]], $batch['rows']);
        self::assertFalse($batch['hasMore']);
    }

    public function test_fetch_reports_has_more_rows(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = self::executeStatementResponse();
        $client->getResultSetMetadataResponse = self::metadataResponse();
        $client->fetchResponses = [
            new TFetchResultsResp([
                'status' => self::successStatus(),
                'hasMoreRows' => true,
                'results' => new TRowSet(['startRowOffset' => 0, 'rows' => []]),
            ]),
            self::emptyFetchResponse(),
        ];

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $transport->execute('SELECT id, name FROM users');

        self::assertTrue($transport->fetch(1000)['hasMore']);
        self::assertArrayNotHasKey('CloseOperation', $client->requests);

        self::assertFalse($transport->fetch(1000)['hasMore']);
        self::assertSame(1000, $client->requests['FetchResults'][0]->maxRows);
        self::assertSame(TFetchOrientation::FETCH_NEXT, $client->requests['FetchResults'][0]->orientation);
    }

    public function test_fetch_closes_operation_once_exhausted(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = self::executeStatementResponse();
        $client->getResultSetMetadataResponse = self::metadataResponse();
        $client->fetchResponses = [self::columnarFetchResponse()];
        $client->closeOperationResponse = new TCloseOperationResp(['status' => self::successStatus()]);

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $transport->execute('SELECT id, name FROM users');

        self::assertFalse($transport->fetch(1000)['hasMore']);
        self::assertArrayHasKey('CloseOperation', $client->requests);
        self::assertInstanceOf(TCloseOperationReq::class, $client->requests['CloseOperation'][0]);
        self::assertSame(
            $client->executeStatementResponse->operationHandle,
            $client->requests['CloseOperation'][0]->operationHandle,
        );

        // The cleared operation must not be fetched from again.
        self::assertSame(['rows' => [], 'hasMore' => false], $transport->fetch(1000));
        self::assertCount(1, $client->requests['FetchResults']);
    }

    public function test_fetch_returns_rows_when_close_operation_fails(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = self::executeStatementResponse();
        $client->getResultSetMetadataResponse = self::metadataResponse();
        $client->fetchResponses = [self::columnarFetchResponse()];
        $client->closeOperationException = new TTransportException('connection gone');

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $transport->execute('SELECT id, name FROM users');
        $batch = $transport->fetch(1000);

        self::assertSame([[1, 'a'], [null, 'b'], [3, null]], $batch['rows']);
        self::assertFalse($batch['hasMore']);
    }

    public function test_binary_transport_uses_tls_socket_when_enabled(): void
    {
        $socket = self::inspectSocket(new ThriftTransport(HiveConfig::fromArray([
            'host' => 'hive.local',
            'tls' => true,
        ])));

        self::assertInstanceOf(TSSLSocket::class, $socket);
        self::assertSame('ssl://hive.local', $socket->getHost());

        $options = stream_context_get_options(self::sslContext($socket));
        self::assertTrue($options['ssl']['verify_peer']);
        self::assertTrue($options['ssl']['verify_peer_name']);
    }

    public function test_tls_transport_disables_verification_when_verify_ssl_is_false(): void
    {
        $socket = self::inspectSocket(new ThriftTransport(HiveConfig::fromArray([
            'host' => 'hive.local',
            'tls' => true,
            'verify_ssl' => false,
        ])));

        self::assertInstanceOf(TSSLSocket::class, $socket);

        $options = stream_context_get_options(self::sslContext($socket));
        self::assertFalse($options['ssl']['verify_peer']);
        self::assertFalse($options['ssl']['verify_peer_name']);
    }

    public function test_http_transport_scheme_is_unaffected_by_tls_option(): void
    {
        $transport = new ThriftTransport(HiveConfig::fromArray([
            'host' => 'hive.local',
            'transport' => 'http',
            'scheme' => 'https',
            'tls' => true,
        ]));

        $method = new \ReflectionMethod($transport, 'createTransport');
        $http = $method->invoke($transport);

        self::assertInstanceOf(THttpClient::class, $http);
        $scheme = new \ReflectionProperty(THttpClient::class, 'scheme');
        self::assertSame('https', $scheme->getValue($http));
    }

    public function test_binary_socket_timeouts_convert_config_seconds_to_milliseconds(): void
    {
        foreach ([false, true] as $tls) {
            $socket = self::inspectSocket(new ThriftTransport(HiveConfig::fromArray([
                'host' => 'hive.local',
                'timeout' => 2.5,
                'tls' => $tls,
            ])));

            foreach (['connect', 'send', 'recv'] as $prefix) {
                self::assertSame(2500, self::timeoutMillis($socket, $prefix), $prefix . ' timeout');
            }
        }
    }

    private static function inspectSocket(ThriftTransport $transport): TSocket
    {
        $method = new \ReflectionMethod($transport, 'createTransport');
        $wrapped = $method->invoke($transport);

        self::assertInstanceOf(TBufferedTransport::class, $wrapped);
        $inner = new \ReflectionProperty(TBufferedTransport::class, 'transport');
        $socket = $inner->getValue($wrapped);

        self::assertInstanceOf(TSocket::class, $socket);

        return $socket;
    }

    /**
     * @return resource
     */
    private static function sslContext(TSSLSocket $socket)
    {
        $context = new \ReflectionProperty(TSSLSocket::class, 'context');

        return $context->getValue($socket);
    }

    private static function timeoutMillis(TSocket $socket, string $prefix): int
    {
        $sec = new \ReflectionProperty(TSocket::class, $prefix . 'TimeoutSec');
        $usec = new \ReflectionProperty(TSocket::class, $prefix . 'TimeoutUsec');

        return $sec->getValue($socket) * 1000 + intdiv((int) $usec->getValue($socket), 1000);
    }

    public function test_fetch_supports_row_based_results(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = self::executeStatementResponse();
        $client->getResultSetMetadataResponse = self::metadataResponse();
        $client->fetchResponses = [
            new TFetchResultsResp([
                'status' => self::successStatus(),
                'hasMoreRows' => false,
                'results' => new TRowSet([
                    'startRowOffset' => 0,
                    'rows' => [
                        new TRow(['colVals' => [
                            new TColumnValue(['i32Val' => new TI32Value(['value' => 7])]),
                            new TColumnValue(['stringVal' => new TStringValue(['value' => 'x'])]),
                        ]]),
                        new TRow(['colVals' => [
                            new TColumnValue(['i32Val' => new TI32Value()]),
                            new TColumnValue(['stringVal' => new TStringValue(['value' => 'y'])]),
                        ]]),
                    ],
                ]),
            ]),
        ];

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $transport->execute('SELECT id, name FROM users');

        self::assertSame([[7, 'x'], [null, 'y']], $transport->fetch(1000)['rows']);
    }

    public function test_fetch_without_result_set_returns_empty_batch(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = new TExecuteStatementResp([
            'status' => self::successStatus(),
            'operationHandle' => self::operationHandle(false),
        ]);

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $result = $transport->execute('SET a=b');

        self::assertSame([], $result['columns']);
        self::assertSame(['rows' => [], 'hasMore' => false], $transport->fetch(1000));
        self::assertArrayNotHasKey('FetchResults', $client->requests);
    }

    public function test_error_status_throws_request_exception(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = new TExecuteStatementResp([
            'status' => new TStatus([
                'statusCode' => TStatusCode::ERROR_STATUS,
                'sqlState' => '42000',
                'errorCode' => 10001,
                'errorMessage' => 'Invalid table alias',
            ]),
        ]);

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);

        try {
            $transport->execute('SELECT * FROM broken');
            self::fail('Expected RequestException');
        } catch (RequestException $e) {
            self::assertStringContainsString('Invalid table alias', $e->getMessage());
            self::assertSame('42000', $e->sqlState);
            self::assertSame(10001, $e->errorCode);
        }
    }

    public function test_transport_failure_from_execute_wraps_and_resets_state(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementException = new TTransportException('socket dropped');

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);

        try {
            $transport->execute('SELECT 1');
            self::fail('Expected ConnectionException');
        } catch (ConnectionException $e) {
            self::assertStringContainsString('socket dropped', $e->getMessage());
            self::assertInstanceOf(TTransportException::class, $e->getPrevious());
        }

        self::assertSame(['rows' => [], 'hasMore' => false], $transport->fetch(1000));
        self::assertArrayNotHasKey('FetchResults', $client->requests);

        $client->executeStatementException = null;
        $transport->open();

        self::assertCount(2, $client->requests['OpenSession']);
    }

    public function test_application_failure_from_fetch_wraps_as_request_exception_and_resets_state(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->executeStatementResponse = self::executeStatementResponse();
        $client->getResultSetMetadataResponse = self::metadataResponse();
        $client->fetchResultsException = new TApplicationException('bad frame');

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $transport->execute('SELECT id, name FROM users');

        try {
            $transport->fetch(1000);
            self::fail('Expected RequestException');
        } catch (RequestException $e) {
            self::assertStringContainsString('bad frame', $e->getMessage());
            self::assertInstanceOf(TApplicationException::class, $e->getPrevious());
        }

        self::assertSame(['rows' => [], 'hasMore' => false], $transport->fetch(1000));

        $client->fetchResultsException = null;
        $transport->open();

        self::assertCount(2, $client->requests['OpenSession']);
    }

    public function test_close_closes_session(): void
    {
        $client = new ScriptedClient();
        $client->openSessionResponse = self::openSessionResponse();
        $client->closeSessionResponse = new TCloseSessionResp(['status' => self::successStatus()]);

        $transport = new ThriftTransport(HiveConfig::fromArray(['host' => 'hive.local']), $client);
        $transport->open();
        $transport->close();

        self::assertArrayHasKey('CloseSession', $client->requests);
        self::assertSame(
            $client->openSessionResponse->sessionHandle,
            $client->requests['CloseSession'][0]->sessionHandle,
        );
    }

    private static function successStatus(): TStatus
    {
        return new TStatus(['statusCode' => TStatusCode::SUCCESS_STATUS]);
    }

    private static function openSessionResponse(): TOpenSessionResp
    {
        return new TOpenSessionResp([
            'status' => self::successStatus(),
            'serverProtocolVersion' => TProtocolVersion::HIVE_CLI_SERVICE_PROTOCOL_V10,
            'sessionHandle' => new TSessionHandle([
                'sessionId' => new THandleIdentifier(['guid' => 'session-guid', 'secret' => 'session-secret']),
            ]),
        ]);
    }

    private static function executeStatementResponse(): TExecuteStatementResp
    {
        return new TExecuteStatementResp([
            'status' => self::successStatus(),
            'operationHandle' => self::operationHandle(true),
        ]);
    }

    private static function operationHandle(bool $hasResultSet): TOperationHandle
    {
        return new TOperationHandle([
            'operationId' => new THandleIdentifier(['guid' => 'op-guid', 'secret' => 'op-secret']),
            'operationType' => TOperationType::EXECUTE_STATEMENT,
            'hasResultSet' => $hasResultSet,
        ]);
    }

    private static function metadataResponse(): TGetResultSetMetadataResp
    {
        return new TGetResultSetMetadataResp([
            'status' => self::successStatus(),
            'schema' => new TTableSchema([
                'columns' => [
                    new TColumnDesc([
                        'columnName' => 'id',
                        'typeDesc' => new TTypeDesc([
                            'types' => [new TTypeEntry([
                                'primitiveEntry' => new TPrimitiveTypeEntry(['type' => TTypeId::INT_TYPE]),
                            ])],
                        ]),
                        'position' => 1,
                    ]),
                    new TColumnDesc([
                        'columnName' => 'name',
                        'typeDesc' => new TTypeDesc([
                            'types' => [new TTypeEntry([
                                'primitiveEntry' => new TPrimitiveTypeEntry(['type' => TTypeId::STRING_TYPE]),
                            ])],
                        ]),
                        'position' => 2,
                    ]),
                ],
            ]),
        ]);
    }

    private static function columnarFetchResponse(): TFetchResultsResp
    {
        return new TFetchResultsResp([
            'status' => self::successStatus(),
            'hasMoreRows' => false,
            'results' => new TRowSet([
                'startRowOffset' => 0,
                'rows' => [],
                'columns' => [
                    new TColumn(['i32Val' => new TI32Column([
                        'values' => [1, 0, 3],
                        'nulls' => "\x02",
                    ])]),
                    new TColumn(['stringVal' => new TStringColumn([
                        'values' => ['a', 'b', ''],
                        'nulls' => "\x04",
                    ])]),
                ],
            ]),
        ]);
    }

    private static function emptyFetchResponse(): TFetchResultsResp
    {
        return new TFetchResultsResp([
            'status' => self::successStatus(),
            'hasMoreRows' => false,
            'results' => new TRowSet(['startRowOffset' => 0, 'rows' => []]),
        ]);
    }
}

final class ScriptedClient extends TCLIServiceClient
{
    /** @var array<string, list<object>> */
    public array $requests = [];

    public ?TOpenSessionResp $openSessionResponse = null;

    public ?TExecuteStatementResp $executeStatementResponse = null;

    public ?TGetResultSetMetadataResp $getResultSetMetadataResponse = null;

    public ?TCloseSessionResp $closeSessionResponse = null;

    public ?TCloseOperationResp $closeOperationResponse = null;

    /** @var list<TFetchResultsResp> */
    public array $fetchResponses = [];

    public ?\Throwable $executeStatementException = null;

    public ?\Throwable $fetchResultsException = null;

    public ?\Throwable $closeOperationException = null;

    public function __construct()
    {
    }

    public function OpenSession(TOpenSessionReq $req)
    {
        $this->requests['OpenSession'][] = $req;

        return $this->openSessionResponse;
    }

    public function ExecuteStatement(TExecuteStatementReq $req)
    {
        $this->requests['ExecuteStatement'][] = $req;

        if ($this->executeStatementException !== null) {
            throw $this->executeStatementException;
        }

        return $this->executeStatementResponse;
    }

    public function GetResultSetMetadata(TGetResultSetMetadataReq $req)
    {
        $this->requests['GetResultSetMetadata'][] = $req;

        return $this->getResultSetMetadataResponse;
    }

    public function FetchResults(TFetchResultsReq $req)
    {
        $this->requests['FetchResults'][] = $req;

        if ($this->fetchResultsException !== null) {
            throw $this->fetchResultsException;
        }

        return array_shift($this->fetchResponses);
    }

    public function CloseOperation(TCloseOperationReq $req)
    {
        $this->requests['CloseOperation'][] = $req;

        if ($this->closeOperationException !== null) {
            throw $this->closeOperationException;
        }

        return $this->closeOperationResponse;
    }

    public function CloseSession(TCloseSessionReq $req)
    {
        $this->requests['CloseSession'][] = $req;

        return $this->closeSessionResponse;
    }
}
