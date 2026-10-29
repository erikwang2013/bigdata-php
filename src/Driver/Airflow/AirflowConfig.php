<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Airflow;

use Erikwang2013\BigData\Support\Config;

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
        $baseUrl = Config::string($config, ['baseUrl', 'base_url']);
        if ($baseUrl === null) {
            throw new \InvalidArgumentException('Airflow config requires a non-empty "baseUrl".');
        }

        return new self(
            baseUrl: $baseUrl,
            username: Config::string($config, ['username']),
            password: Config::string($config, ['password']),
            token: Config::string($config, ['token']),
            apiPath: Config::string($config, ['api_path'], '/api/v1'),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }

    public function baseUri(): string
    {
        return rtrim($this->baseUrl, '/') . '/' . trim($this->apiPath, '/');
    }
}
