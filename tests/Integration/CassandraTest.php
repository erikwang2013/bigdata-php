<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Integration;

use Erikwang2013\BigData\Driver\Cassandra\CassandraClient;
use Erikwang2013\BigData\Driver\Cassandra\CassandraConfig;
use Erikwang2013\BigData\Driver\Cassandra\ResultSet;
use PHPUnit\Framework\TestCase;

final class CassandraTest extends TestCase
{
    private const KEYSPACE = 'bigdata_php_test';

    private ?CassandraClient $client = null;

    protected function setUp(): void
    {
        $host = getenv('CASSANDRA_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('CASSANDRA_HOST is not set; start demo/cassandra/docker-compose.yml first.');
        }

        $this->client = new CassandraClient(
            CassandraConfig::fromArray(['contact_points' => [$host . ':9042']]),
        );
    }

    protected function tearDown(): void
    {
        $this->client?->close();
        $this->client = null;
    }

    public function test_select_release_version_from_system_local(): void
    {
        $client = $this->client();
        $result = $client->query('SELECT release_version FROM system.local');

        self::assertGreaterThanOrEqual(1, $result->count());
        self::assertArrayHasKey('release_version', $result->rows()[0]);
        self::assertNotNull($result->rows()[0]['release_version']);

        $pagingState = $result->pagingState();
        self::assertTrue($pagingState === null || is_string($pagingState));
    }

    public function test_prepared_statement_returns_system_local_row(): void
    {
        $client = $this->client();
        $statement = $client->prepare('SELECT release_version FROM system.local WHERE key = ?');
        $result = $client->execute($statement, ['local']);

        self::assertGreaterThanOrEqual(1, $result->count());
        self::assertArrayHasKey('release_version', $result->rows()[0]);

        $columns = $statement->columns();
        self::assertNotEmpty($columns);
        self::assertSame('key', $columns[0]['name']);
        self::assertArrayHasKey('type', $columns[0]);
    }

    public function test_full_write_path(): void
    {
        $client = $this->client();

        try {
            $client->query(sprintf(
                "CREATE KEYSPACE IF NOT EXISTS %s WITH replication = {'class': 'SimpleStrategy', 'replication_factor': 1}",
                self::KEYSPACE,
            ));
            self::assertEmptyResultSet($client->query('USE ' . self::KEYSPACE));

            $client->query('DROP TABLE IF EXISTS items');
            self::assertEmptyResultSet($client->query('CREATE TABLE items (id int PRIMARY KEY, name text)'));

            $insert = $client->prepare('INSERT INTO items (id, name) VALUES (?, ?)');
            self::assertEmptyResultSet($client->execute($insert, [1, 'alice']));

            $select = $client->prepare('SELECT id, name FROM items WHERE id = ?');
            self::assertSame([['id' => 1, 'name' => 'alice']], $client->execute($select, [1])->rows());

            $update = $client->prepare('UPDATE items SET name = ? WHERE id = ?');
            self::assertEmptyResultSet($client->execute($update, ['bob', 1]));
            self::assertSame('bob', $client->execute($select, [1])->rows()[0]['name']);

            $delete = $client->prepare('DELETE FROM items WHERE id = ?');
            self::assertEmptyResultSet($client->execute($delete, [1]));
            self::assertSame(0, $client->execute($select, [1])->count());
        } finally {
            try {
                $client->query('DROP TABLE IF EXISTS items');
                $client->query('DROP KEYSPACE IF EXISTS ' . self::KEYSPACE);
            } catch (\Throwable) {
                // Cleanup is best-effort; a failed drop must not mask the test result.
            }
        }
    }

    private static function assertEmptyResultSet(ResultSet $result): void
    {
        self::assertSame([], $result->columns());
        self::assertSame([], $result->rows());
        self::assertSame([], $result->raw());
        self::assertSame(0, $result->count());
        self::assertNull($result->pagingState());
    }

    private function client(): CassandraClient
    {
        self::assertNotNull($this->client);

        return $this->client;
    }
}
