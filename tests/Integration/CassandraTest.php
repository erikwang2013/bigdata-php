<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Integration;

use Erikwang2013\BigData\Driver\Cassandra\CassandraClient;
use Erikwang2013\BigData\Driver\Cassandra\CassandraConfig;
use PHPUnit\Framework\TestCase;

final class CassandraTest extends TestCase
{
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
    }

    private function client(): CassandraClient
    {
        self::assertNotNull($this->client);

        return $this->client;
    }
}
