<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Integration;

use Erikwang2013\BigData\Driver\Hive\HiveClient;
use Erikwang2013\BigData\Driver\Hive\HiveConfig;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
final class HiveTest extends TestCase
{
    public function test_select_one_returns_a_single_row(): void
    {
        $host = getenv('HIVE_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('HIVE_HOST is not set; start HiveServer2 first.');
        }

        $client = new HiveClient(HiveConfig::fromArray([
            'host' => $host,
            'port' => 10000,
            'auth' => 'NONE',
        ]));

        try {
            $result = $client->execute('SELECT 1 AS id');
        } finally {
            $client->close();
        }

        self::assertCount(1, $result->rows());
        self::assertCount(1, $result->rows()[0]);
        self::assertSame(1, (int) $result->rows()[0][0]);
    }
}
