<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hive;

use Erikwang2013\BigData\Driver\Hive\HiveConfig;
use PHPUnit\Framework\TestCase;

final class HiveConfigTest extends TestCase
{
    public function test_from_array_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HiveConfig::fromArray([]);
    }

    public function test_from_array_rejects_blank_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HiveConfig::fromArray(['host' => '']);
    }

    public function test_defaults(): void
    {
        $config = HiveConfig::fromArray(['host' => 'hive.local']);

        self::assertSame('hive.local', $config->host);
        self::assertSame(10000, $config->port);
        self::assertSame('php', $config->username);
        self::assertNull($config->password);
        self::assertSame('NONE', $config->auth);
        self::assertSame('binary', $config->transport);
        self::assertSame('/cliservice', $config->httpPath);
        self::assertSame('default', $config->database);
        self::assertTrue($config->verifySsl);
        self::assertSame(30.0, $config->timeout);
        self::assertSame('http', $config->scheme);
        self::assertFalse($config->tls);
    }

    public function test_from_array_parses_all_fields(): void
    {
        $config = HiveConfig::fromArray([
            'host' => 'hive.local',
            'port' => 10001,
            'username' => 'alice',
            'password' => 'secret',
            'auth' => 'ldap',
            'transport' => 'HTTP',
            'http_path' => '/gateway',
            'database' => 'analytics',
            'verify_ssl' => false,
            'timeout' => 5.5,
            'scheme' => 'HTTPS',
            'tls' => true,
        ]);

        self::assertSame(10001, $config->port);
        self::assertSame('alice', $config->username);
        self::assertSame('secret', $config->password);
        self::assertSame('LDAP', $config->auth);
        self::assertSame('http', $config->transport);
        self::assertSame('/gateway', $config->httpPath);
        self::assertSame('analytics', $config->database);
        self::assertFalse($config->verifySsl);
        self::assertSame(5.5, $config->timeout);
        self::assertSame('https', $config->scheme);
        self::assertTrue($config->tls);
    }

    public function test_from_array_accepts_http_scheme_alias(): void
    {
        $config = HiveConfig::fromArray(['host' => 'hive.local', 'http_scheme' => 'https']);

        self::assertSame('https', $config->scheme);
    }

    public function test_from_array_rejects_unknown_scheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HiveConfig::fromArray(['host' => 'hive.local', 'scheme' => 'ftp']);
    }

    public function test_from_array_rejects_unknown_auth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HiveConfig::fromArray(['host' => 'hive.local', 'auth' => 'KERBEROS']);
    }

    public function test_from_array_rejects_unknown_transport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HiveConfig::fromArray(['host' => 'hive.local', 'transport' => 'framed']);
    }
}
