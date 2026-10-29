<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\HBase;

use Erikwang2013\BigData\Driver\HBase\HBaseConfig;
use PHPUnit\Framework\TestCase;

final class HBaseConfigTest extends TestCase
{
    public function test_snake_case_base_url_alias(): void
    {
        $config = HBaseConfig::fromArray(['base_url' => 'http://hbase.local:8080/']);

        self::assertSame('http://hbase.local:8080', $config->baseUrl);
        self::assertTrue($config->verifySsl);
        self::assertNull($config->username);
    }

    public function test_camel_case_base_url_and_verify_ssl_aliases(): void
    {
        $config = HBaseConfig::fromArray([
            'baseUrl' => 'http://hbase.local:8080',
            'username' => 'alice',
            'password' => 'secret',
            'verifySsl' => false,
            'timeout' => 5.0,
        ]);

        self::assertSame('http://hbase.local:8080', $config->baseUrl);
        self::assertSame('alice', $config->username);
        self::assertSame('secret', $config->password);
        self::assertFalse($config->verifySsl);
        self::assertSame(5.0, $config->timeout);
    }

    public function test_requires_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HBaseConfig::fromArray([]);
    }
}
