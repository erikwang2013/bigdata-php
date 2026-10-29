<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Iceberg;

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
        if (!isset($config['base_url']) || !is_string($config['base_url']) || $config['base_url'] === '') {
            throw new \InvalidArgumentException('Iceberg config requires a non-empty "base_url".');
        }

        return new self(
            baseUrl: $config['base_url'],
            token: isset($config['token']) ? (string) $config['token'] : null,
            clientId: isset($config['client_id']) ? (string) $config['client_id'] : null,
            clientSecret: isset($config['client_secret']) ? (string) $config['client_secret'] : null,
            scope: (string) ($config['scope'] ?? 'catalog'),
            warehouse: isset($config['warehouse']) ? (string) $config['warehouse'] : null,
            prefix: isset($config['prefix']) ? (string) $config['prefix'] : null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
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
