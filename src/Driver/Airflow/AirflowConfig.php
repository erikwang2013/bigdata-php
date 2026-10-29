<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Airflow;

final class AirflowConfig
{
    public function __construct(
        public string $baseUrl,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $token = null,
        public string $apiPath = '/api/v1',
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['baseUrl']) || $config['baseUrl'] === '') {
            throw new \InvalidArgumentException('Airflow config requires a non-empty "baseUrl".');
        }

        return new self(
            baseUrl: (string) $config['baseUrl'],
            username: isset($config['username']) ? (string) $config['username'] : null,
            password: isset($config['password']) ? (string) $config['password'] : null,
            token: isset($config['token']) ? (string) $config['token'] : null,
            apiPath: (string) ($config['api_path'] ?? '/api/v1'),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }

    public function baseUri(): string
    {
        return rtrim($this->baseUrl, '/') . '/' . trim($this->apiPath, '/');
    }
}
