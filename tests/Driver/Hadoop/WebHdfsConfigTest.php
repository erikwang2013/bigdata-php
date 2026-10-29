<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hadoop;

use Erikwang2013\BigData\Driver\Hadoop\WebHdfsConfig;
use PHPUnit\Framework\TestCase;

final class WebHdfsConfigTest extends TestCase
{
    public function test_defaults(): void
    {
        $config = WebHdfsConfig::fromArray(['host' => 'namenode.local']);

        self::assertSame(9870, $config->port);
        self::assertSame('http', $config->scheme);
        self::assertSame('php', $config->user);
        self::assertTrue($config->verifySsl);
        self::assertSame(30.0, $config->timeout);
    }

    public function test_camel_case_aliases(): void
    {
        $config = WebHdfsConfig::fromArray([
            'host' => 'namenode.local',
            'httpPort' => 9871,
            'delegationToken' => 'token',
            'verifySsl' => false,
        ]);

        self::assertSame(9871, $config->port);
        self::assertSame('token', $config->delegationToken);
        self::assertFalse($config->verifySsl);
    }

    public function test_snake_case_http_port_alias(): void
    {
        $config = WebHdfsConfig::fromArray(['host' => 'namenode.local', 'http_port' => 9872]);

        self::assertSame(9872, $config->port);
        self::assertSame('http://namenode.local:9872/webhdfs/v1', $config->baseUri());
    }

    public function test_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebHdfsConfig::fromArray([]);
    }
}
