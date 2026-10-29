<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\StarRocks;

use Erikwang2013\BigData\Driver\StarRocks\StarRocksConfig;
use PHPUnit\Framework\TestCase;

final class StarRocksConfigTest extends TestCase
{
    public function test_dsn_built_from_config(): void
    {
        $config = StarRocksConfig::fromArray(['host' => 'starrocks.local', 'database' => 'demo']);

        self::assertSame('mysql:host=starrocks.local;port=9030;dbname=demo;charset=utf8mb4', $config->dsn());
        self::assertSame(8030, $config->httpPort);
        self::assertSame('root', $config->username);
        self::assertSame('', $config->password);
        self::assertTrue($config->verifySsl);
    }

    public function test_from_array_overrides_defaults(): void
    {
        $config = StarRocksConfig::fromArray([
            'host' => 'starrocks.local',
            'port' => 9031,
            'http_port' => 8031,
            'database' => 'analytics',
            'username' => 'etl',
            'password' => 'secret',
            'charset' => 'utf8',
            'verify_ssl' => false,
            'timeout' => 5.0,
        ]);

        self::assertSame('mysql:host=starrocks.local;port=9031;dbname=analytics;charset=utf8', $config->dsn());
        self::assertSame(8031, $config->httpPort);
        self::assertSame('etl', $config->username);
        self::assertSame('secret', $config->password);
        self::assertFalse($config->verifySsl);
        self::assertSame(5.0, $config->timeout);
    }

    public function test_from_array_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StarRocksConfig::fromArray(['database' => 'demo']);
    }

    public function test_from_array_requires_database(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StarRocksConfig::fromArray(['host' => 'starrocks.local']);
    }
}
