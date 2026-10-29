<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Trino;

use Erikwang2013\BigData\Driver\Trino\TrinoConfig;
use PHPUnit\Framework\TestCase;

final class TrinoConfigTest extends TestCase
{
    public function test_from_array_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TrinoConfig::fromArray([]);
    }

    public function test_defaults_and_base_uri(): void
    {
        $config = TrinoConfig::fromArray(['host' => 'trino.local']);

        self::assertSame('http://trino.local:8080', $config->baseUri());
        self::assertSame('php', $config->user);
        self::assertTrue($config->verifySsl);
    }

    public function test_verify_ssl_aliases_are_accepted(): void
    {
        self::assertFalse(TrinoConfig::fromArray(['host' => 'trino.local', 'verify_ssl' => false])->verifySsl);
        self::assertFalse(TrinoConfig::fromArray(['host' => 'trino.local', 'verifySsl' => false])->verifySsl);
    }
}
