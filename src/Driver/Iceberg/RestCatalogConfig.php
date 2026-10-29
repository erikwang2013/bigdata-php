<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Iceberg;

use Erikwang2013\BigData\Support\Config;

final class RestCatalogConfig
{
    public function __construct(
        public string $baseUrl,
        public ?string $token = null,
        public ?string $clientId = null,
        public ?string $clientSecret = null,
        public string $scope = 'catalog',
        public ?string $warehouse = null,
        public ?string $prefix = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $baseUrl = Config::string($config, ['base_url', 'baseUrl']);
        if ($baseUrl === null) {
            throw new \InvalidArgumentException('Iceberg config requires a non-empty "base_url".');
        }

        return new self(
            baseUrl: $baseUrl,
            token: Config::string($config, ['token']),
            clientId: Config::string($config, ['client_id']),
            clientSecret: Config::string($config, ['client_secret']),
            scope: Config::string($config, ['scope'], 'catalog') ?? 'catalog',
            warehouse: Config::string($config, ['warehouse']),
            prefix: Config::string($config, ['prefix']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }

    /**
     * The Iceberg REST API root. The catalog prefix is NOT part of it: /v1/config
     * and /v1/oauth/tokens live outside the prefix, only resource paths use it.
     */
    public function baseUri(): string
    {
        return rtrim($this->baseUrl, '/') . '/v1';
    }
}
