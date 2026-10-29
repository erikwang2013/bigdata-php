<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hadoop;

use Erikwang2013\BigData\Driver\Hadoop\YarnConfig;
use PHPUnit\Framework\TestCase;

final class YarnConfigTest extends TestCase
{
    public function test_defaults(): void
    {
        $config = YarnConfig::fromArray(['host' => 'rm.local']);

        self::assertSame(8088, $config->port);
        self::assertSame('http', $config->scheme);
        self::assertSame('php', $config->user);
        self::assertTrue($config->verifySsl);
        self::assertSame('http://rm.local:8088', $config->baseUri());
    }

    public function test_camel_case_aliases(): void
    {
        $config = YarnConfig::fromArray([
            'host' => 'rm.local',
            'httpPort' => 8089,
            'verifySsl' => false,
            'timeout' => 5.0,
        ]);

        self::assertSame(8089, $config->port);
        self::assertFalse($config->verifySsl);
        self::assertSame(5.0, $config->timeout);
    }

    public function test_snake_case_http_port_alias(): void
    {
        self::assertSame(8090, YarnConfig::fromArray([
            'host' => 'rm.local',
            'http_port' => 8090,
        ])->port);
    }

    public function test_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        YarnConfig::fromArray([]);
    }
}
