<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

use Erikwang2013\BigData\Driver\Cassandra\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Cassandra\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class CassandraClient
{
    private readonly SessionInterface $session;

    private readonly LoggerInterface $logger;

    private bool $connected = false;

    public function __construct(
        private readonly CassandraConfig $config,
        ?SessionInterface $session = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->session = $session ?? new PhpCassandraSession($config);
        $this->logger = $logger ?? new NullLogger();
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            $this->session->connect();
            $this->connected = true;
        } catch (ConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException('Cassandra connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function query(
        string $cql,
        array $params = [],
        int $consistency = Consistency::ONE,
    ): ResultSet {
        $this->connect();
        $this->logger->debug('Cassandra query', ['consistency' => $consistency]);

        return $this->call(fn (): ResultSet => $this->session->query($cql, $params, $consistency));
    }

    public function prepare(string $cql): PreparedStatement
    {
        $this->connect();
        $this->logger->debug('Cassandra prepare');

        return $this->call(fn (): PreparedStatement => $this->session->prepare($cql));
    }

    public function execute(
        PreparedStatement $statement,
        array $params = [],
        int $consistency = Consistency::ONE,
    ): ResultSet {
        $this->connect();
        $this->logger->debug('Cassandra execute', ['consistency' => $consistency]);

        return $this->call(fn (): ResultSet => $this->session->execute($statement, $params, $consistency));
    }

    public function close(): void
    {
        if (!$this->connected) {
            return;
        }

        $this->session->close();
        $this->connected = false;
    }

    private function call(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ConnectionException | RequestException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RequestException($e->getMessage(), null, '', $e);
        }
    }
}
