<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Integration;

use Erikwang2013\BigData\Driver\StarRocks\StarRocksClient;
use Erikwang2013\BigData\Driver\StarRocks\StarRocksConfig;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
final class StarRocksTest extends TestCase
{
    public function test_select_one_returns_a_single_row(): void
    {
        $host = getenv('STARROCKS_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('STARROCKS_HOST is not set; start StarRocks allin1 first.');
        }
        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('ext-pdo_mysql is required by the StarRocks driver.');
        }

        // The allin1 image ships no user database, but information_schema is always present.
        $client = new StarRocksClient(StarRocksConfig::fromArray([
            'host' => $host,
            'database' => 'information_schema',
            'port' => 9030,
        ]));

        $result = $client->query('SELECT 1 AS id');

        self::assertCount(1, $result->rows());
        self::assertSame(1, (int) $result->rows()[0]['id']);
    }
}
