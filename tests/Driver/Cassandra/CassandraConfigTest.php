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
}
