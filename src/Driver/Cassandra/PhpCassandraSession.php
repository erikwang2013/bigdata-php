<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

use Cassandra\Connection;
use Cassandra\Connection\ConnectionOptions;
use Cassandra\Connection\NodeSelectionStrategy;
use Cassandra\Connection\StreamNodeConfig;
use Cassandra\Consistency as DriverConsistency;
use Cassandra\Protocol\ProtocolVersion;
use Cassandra\Request\Options\ExecuteOptions;
use Cassandra\Request\Options\QueryOptions;
use Cassandra\Response\Result\FetchType;
use Erikwang2013\BigData\Driver\Cassandra\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Cassandra\Exception\RequestException;

/**
 * Adapter around mroosz/php-cassandra. The driver is an optional dependency;
 * install it with `composer require mroosz/php-cassandra` before using this class.
 *
 * @internal
 */
final class PhpCassandraSession implements SessionInterface
{
    private ?Connection $connection = null;

    public function __construct(private CassandraConfig $config)
    {
    }

    public function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        try {
            $nodes = [];
            foreach ($this->config->contactPoints as $point) {
                [$host, $port] = $this->splitContactPoint($point);
                $nodes[] = new StreamNodeConfig(
                    host: $host,
                    port: $port,
                    username: $this->config->username,
                    password: $this->config->password,
                    connectTimeoutInSeconds: $this->config->connectTimeout,
                    timeoutInSeconds: $this->config->timeout,
                    sslOptions: $this->config->tls
                        ? [
                            'verify_peer' => $this->config->verifySsl,
                            'verify_peer_name' => $this->config->verifySsl,
                        ]
                        : [],
                );
            }

            $this->connection = new Connection(
                $nodes,
                $this->config->keyspace,
                $this->connectionOptions(),
            );
            $this->connection->connect();
        } catch (\Throwable $e) {
            $this->connection = null;
            throw new ConnectionException('Cassandra connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function query(string $cql, array $params, int $consistency, ?int $pageSize = null, ?string $pagingState = null): ResultSet
    {
        $this->connect();

        try {
            $result = $this->connection->query(
                $cql,
                $params,
                DriverConsistency::from($consistency),
                new QueryOptions(pageSize: $pageSize, pagingState: $pagingState),
            );

            return $this->mapResult($result);
        } catch (\Cassandra\Exception\ConnectionException $e) {
            $this->connection = null;
            throw new ConnectionException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new RequestException($e->getMessage(), null, '', $e);
        }
    }

    public function prepare(string $cql): PreparedStatement
    {
        $this->connect();

        try {
            return new PreparedStatement($cql, $this->connection->prepare($cql));
        } catch (\Cassandra\Exception\ConnectionException $e) {
            $this->connection = null;
            throw new ConnectionException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new RequestException($e->getMessage(), null, '', $e);
        }
    }

    public function execute(PreparedStatement $statement, array $params, int $consistency, ?int $pageSize = null, ?string $pagingState = null): ResultSet
    {
        $this->connect();

        try {
            $result = $this->connection->execute(
                $statement->driverResult(),
                $params,
                DriverConsistency::from($consistency),
                new ExecuteOptions(pageSize: $pageSize, pagingState: $pagingState),
            );

            return $this->mapResult($result);
        } catch (\Cassandra\Exception\ConnectionException $e) {
            $this->connection = null;
            throw new ConnectionException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new RequestException($e->getMessage(), null, '', $e);
        }
    }

    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    /**
     * INSERT/UPDATE/DELETE return VoidResult, DDL returns SchemaChangeResult and
     * USE returns SetKeyspaceResult; none of them can be read as rows.
     */
    private function mapResult(\Cassandra\Response\Result $result): ResultSet
    {
        if ($result instanceof \Cassandra\Response\Result\RowsResult) {
            return $this->toResultSet($result);
        }

        return new ResultSet([], [], []);
    }

    private function toResultSet(\Cassandra\Response\Result\RowsResult $result): ResultSet
    {
        $rows = $result->fetchAll(FetchType::ASSOC);
        $columns = [];
        if ($rows !== []) {
            foreach (array_keys($rows[0]) as $name) {
                $columns[] = ['name' => (string) $name];
            }
        }

        $pagingState = $result->getRowsMetadata()->pagingState;

        return new ResultSet($columns, $rows, $rows, is_string($pagingState) ? $pagingState : null);
    }

    /** @return array{0: string, 1: int} */
    private function splitContactPoint(string $point): array
    {
        if (str_starts_with($point, '[')) {
            $end = strpos($point, ']');
            if ($end !== false) {
                $host = substr($point, 1, $end - 1);
                $port = substr($point, $end + 1);
                $port = ltrim($port, ':');

                return [$host, $port === '' ? 9042 : (int) $port];
            }
        }

        $pos = strrpos($point, ':');
        if ($pos === false) {
            return [$point, 9042];
        }

        return [substr($point, 0, $pos), (int) substr($point, $pos + 1)];
    }

    private function connectionOptions(): ConnectionOptions
    {
        return new ConnectionOptions(
            nodeSelectionStrategy: $this->config->nodeSelection === 'round_robin'
                ? NodeSelectionStrategy::RoundRobin
                : NodeSelectionStrategy::Random,
            initialProtocolVersion: $this->protocolVersion(),
        );
    }

    private function protocolVersion(): ProtocolVersion
    {
        return match ($this->config->protocolVersion) {
            3 => ProtocolVersion::V3,
            5 => ProtocolVersion::V5,
            default => ProtocolVersion::V4,
        };
    }
}
