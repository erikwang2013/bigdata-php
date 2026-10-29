<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Iceberg;

use Erikwang2013\BigData\Driver\Iceberg\RestCatalogConfig;
use PHPUnit\Framework\TestCase;

final class RestCatalogConfigTest extends TestCase
{
    public function test_defaults(): void
    {
        $config = RestCatalogConfig::fromArray(['base_url' => 'https://catalog.example.com/']);

        self::assertSame('catalog', $config->scope);
        self::assertTrue($config->verifySsl);
        self::assertSame('https://catalog.example.com/v1', $config->baseUri());
    }

    public function test_camel_case_aliases(): void
    {
        $config = RestCatalogConfig::fromArray([
            'baseUrl' => 'https://catalog.example.com',
            'token' => 'token',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'verifySsl' => false,
            'timeout' => 5.0,
        ]);

        self::assertSame('https://catalog.example.com', $config->baseUrl);
        self::assertSame('token', $config->token);
        self::assertSame('client', $config->clientId);
        self::assertSame('secret', $config->clientSecret);
        self::assertFalse($config->verifySsl);
        self::assertSame(5.0, $config->timeout);
    }

    public function test_requires_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RestCatalogConfig::fromArray([]);
    }
}
