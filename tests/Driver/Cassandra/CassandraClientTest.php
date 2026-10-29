<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Cassandra;

use Erikwang2013\BigData\Driver\Cassandra\CassandraClient;
use Erikwang2013\BigData\Driver\Cassandra\CassandraConfig;
use Erikwang2013\BigData\Driver\Cassandra\Consistency;
use Erikwang2013\BigData\Driver\Cassandra\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Cassandra\Exception\RequestException;
use Erikwang2013\BigData\Driver\Cassandra\PreparedStatement;
use Erikwang2013\BigData\Driver\Cassandra\ResultSet;
use Erikwang2013\BigData\Driver\Cassandra\SessionInterface;
use PHPUnit\Framework\TestCase;

final class CassandraClientTest extends TestCase
{
    private FakeCassandraSession $session;

    protected function setUp(): void
    {
        $this->session = new FakeCassandraSession();
        $this->session->result = new ResultSet(
            [['name' => 'id'], ['name' => 'name']],
            [['id' => 1, 'name' => 'a'], ['id' => null, 'name' => '']],
            [['id' => 1, 'name' => 'a'], ['id' => null, 'name' => '']],
            'page-1',
        );
    }

    private function client(): CassandraClient
    {
        return new CassandraClient(
            CassandraConfig::fromArray(['contact_points' => ['127.0.0.1:9042']]),
            $this->session,
        );
    }

    public function test_query_delegates_and_returns_rows(): void
    {
        $result = $this->client()->query('SELECT id, name FROM users');

        self::assertSame($this->session->result, $result);
        self::assertSame('SELECT id, name FROM users', $this->session->calls[0]['cql']);
        self::assertSame('page-1', $result->pagingState());
        self::assertCount(2, $result->rows());
    }

    public function test_null_and_empty_values_preserved(): void
    {
        $rows = $this->client()->query('SELECT id, name FROM users')->rows();

        self::assertNull($rows[1]['id']);
        self::assertSame('', $rows[1]['name']);
    }

    public function test_prepare_and_execute_delegate(): void
    {
        $client = $this->client();
        $statement = $client->prepare('SELECT * FROM users WHERE id = ?');
        $result = $client->execute($statement, [1], Consistency::QUORUM);

        self::assertInstanceOf(PreparedStatement::class, $statement);
        self::assertSame('SELECT * FROM users WHERE id = ?', $statement->query());
        self::assertSame($this->session->result, $result);
        self::assertSame(Consistency::QUORUM, $this->session->calls[1]['consistency']);
    }

    public function test_connection_failure_maps_to_connection_exception(): void
    {
        $this->session->connectError = new \RuntimeException('connection refused');

        $this->expectException(ConnectionException::class);
        $this->client()->query('SELECT 1');
    }

    public function test_driver_error_maps_to_request_exception(): void
    {
        $this->session->queryError = new \RuntimeException('bad query');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('bad query');
        $this->client()->query('SELECT x');
    }

    public function test_consistency_is_passed_through(): void
    {
        $this->client()->query('SELECT 1', [], Consistency::LOCAL_QUORUM);

        self::assertSame(Consistency::LOCAL_QUORUM, $this->session->calls[0]['consistency']);
    }
}

final class FakeCassandraSession implements SessionInterface
{
    /** @var list<array{method: string, cql?: string, params?: array, consistency?: int}> */
    public array $calls = [];

    public ?\Throwable $connectError = null;

    public ?\Throwable $queryError = null;

    public ResultSet $result;

    public function connect(): void
    {
        if ($this->connectError !== null) {
            throw $this->connectError;
        }
    }

    public function query(string $cql, array $params, int $consistency): ResultSet
    {
        $this->calls[] = ['method' => 'query', 'cql' => $cql, 'params' => $params, 'consistency' => $consistency];
        if ($this->queryError !== null) {
            throw $this->queryError;
        }

        return $this->result;
    }

    public function prepare(string $cql): PreparedStatement
    {
        $this->calls[] = ['method' => 'prepare', 'cql' => $cql];

        return new PreparedStatement($cql, null);
    }

    public function execute(PreparedStatement $statement, array $params, int $consistency): ResultSet
    {
        $this->calls[] = ['method' => 'execute', 'params' => $params, 'consistency' => $consistency];

        return $this->result;
    }

    public function close(): void
    {
        $this->calls[] = ['method' => 'close'];
    }
}
