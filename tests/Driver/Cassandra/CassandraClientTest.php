<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Cassandra;

use Erikwang2013\BigData\Driver\Cassandra\CassandraClient;
use Erikwang2013\BigData\Driver\Cassandra\CassandraConfig;
use Erikwang2013\BigData\Driver\Cassandra\Consistency;
use Erikwang2013\BigData\Driver\Cassandra\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Cassandra\Exception\RequestException;
use Erikwang2013\BigData\Driver\Cassandra\PhpCassandraSession;
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

    public function test_query_forwards_paging_options(): void
    {
        $this->client()->query('SELECT id FROM users', [], Consistency::ONE, 50, 'opaque-state');

        self::assertSame(50, $this->session->calls[0]['pageSize']);
        self::assertSame('opaque-state', $this->session->calls[0]['pagingState']);
    }

    public function test_execute_forwards_paging_options(): void
    {
        $client = $this->client();
        $statement = $client->prepare('SELECT id FROM users WHERE id = ?');
        $client->execute($statement, [1], Consistency::ONE, 25, 'next-page');

        self::assertSame(25, $this->session->calls[1]['pageSize']);
        self::assertSame('next-page', $this->session->calls[1]['pagingState']);
    }

    public function test_paging_options_default_to_null(): void
    {
        $client = $this->client();
        $client->query('SELECT id FROM users');
        $client->execute($client->prepare('SELECT id FROM users'), []);

        self::assertNull($this->session->calls[0]['pageSize']);
        self::assertNull($this->session->calls[0]['pagingState']);
        self::assertNull($this->session->calls[2]['pageSize']);
        self::assertNull($this->session->calls[2]['pagingState']);
    }

    public function test_prepared_statement_columns_use_driver_prepared_metadata(): void
    {
        $driverResult = new class {
            public function getPreparedData(): object
            {
                return (object) [
                    'prepareMetadata' => (object) [
                        'bindMarkers' => [
                            (object) ['name' => 'id', 'type' => (object) ['type' => (object) ['name' => 'INT']]],
                            (object) ['name' => 'name', 'type' => (object) ['type' => (object) ['name' => 'VARCHAR']]],
                        ],
                    ],
                ];
            }
        };

        $statement = new PreparedStatement('INSERT INTO items (id, name) VALUES (?, ?)', $driverResult);

        self::assertSame([
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'varchar'],
        ], $statement->columns());
    }

    public function test_prepared_statement_columns_are_empty_without_driver_metadata(): void
    {
        $statement = new PreparedStatement('SELECT * FROM users WHERE id = ?', null);

        self::assertSame([], $statement->columns());
    }

    public function test_non_rows_results_map_to_an_empty_result_set(): void
    {
        if (!class_exists(\Cassandra\Response\Result::class)) {
            self::markTestSkipped('mroosz/php-cassandra is not installed.');
        }

        $session = new PhpCassandraSession(
            CassandraConfig::fromArray(['contact_points' => ['127.0.0.1:9042']]),
        );
        $map = new \ReflectionMethod($session, 'mapResult');
        if (PHP_VERSION_ID < 80100) {
            $map->setAccessible(true);
        }

        $result = $map->invoke($session, $this->createMock(\Cassandra\Response\Result::class));

        self::assertSame([], $result->columns());
        self::assertSame([], $result->rows());
        self::assertSame(0, $result->count());
    }
}

final class FakeCassandraSession implements SessionInterface
{
    /** @var list<array{method: string, cql?: string, params?: array, consistency?: int, pageSize?: ?int, pagingState?: ?string}> */
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

    public function query(string $cql, array $params, int $consistency, ?int $pageSize = null, ?string $pagingState = null): ResultSet
    {
        $this->calls[] = [
            'method' => 'query',
            'cql' => $cql,
            'params' => $params,
            'consistency' => $consistency,
            'pageSize' => $pageSize,
            'pagingState' => $pagingState,
        ];
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

    public function execute(PreparedStatement $statement, array $params, int $consistency, ?int $pageSize = null, ?string $pagingState = null): ResultSet
    {
        $this->calls[] = [
            'method' => 'execute',
            'params' => $params,
            'consistency' => $consistency,
            'pageSize' => $pageSize,
            'pagingState' => $pagingState,
        ];

        return $this->result;
    }

    public function close(): void
    {
        $this->calls[] = ['method' => 'close'];
    }
}
