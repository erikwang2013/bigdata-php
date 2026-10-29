<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Cassandra;

use Erikwang2013\BigData\Driver\Cassandra\CassandraConfig;
use PHPUnit\Framework\TestCase;

final class CassandraConfigTest extends TestCase
{
    public function test_requires_contact_points(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CassandraConfig::fromArray([]);
    }

    public function test_defaults(): void
    {
        $config = CassandraConfig::fromArray(['contact_points' => ['127.0.0.1:9042']]);

        self::assertSame(['127.0.0.1:9042'], $config->contactPoints);
        self::assertSame('', $config->keyspace);
        self::assertSame(4, $config->protocolVersion);
        self::assertSame(5.0, $config->connectTimeout);
        self::assertSame(10.0, $config->timeout);
        self::assertFalse($config->tls);
        self::assertTrue($config->verifySsl);
    }

    public function test_rejects_unsupported_protocol_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('protocol_version');

        CassandraConfig::fromArray([
            'contact_points' => ['127.0.0.1:9042'],
            'protocol_version' => 2,
        ]);
    }

    public function test_rejects_unbracketed_ipv6_contact_point(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IPv6');

        CassandraConfig::fromArray(['contact_points' => ['2001:db8::1']]);
    }

    public function test_accepts_bracketed_ipv6_contact_point(): void
    {
        $config = CassandraConfig::fromArray(['contact_points' => ['[2001:db8::1]:9042']]);

        self::assertSame(['[2001:db8::1]:9042'], $config->contactPoints);
    }
}
