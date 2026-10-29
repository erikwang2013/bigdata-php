<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Integration;

use Erikwang2013\BigData\BigData;
use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
final class TrinoTest extends TestCase
{
    public function test_select_one_returns_a_single_row(): void
    {
        $host = getenv('TRINO_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('TRINO_HOST is not set; start demo/docker-compose.yml first.');
        }

        $client = BigData::driver('trino', [
            'host' => $host,
            'port' => 8080,
            'user' => 'php',
        ]);
        self::assertInstanceOf(TrinoClient::class, $client);

        $result = $client->query('SELECT 1 AS id');

        self::assertCount(1, $result->rows());
        self::assertSame(1, $result->rows()[0][0]);
    }
}
