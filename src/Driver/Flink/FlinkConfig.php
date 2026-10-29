<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Flink;

use Erikwang2013\BigData\Support\Config;

final class FlinkConfig
{
    public function __construct(
        public string $baseUrl,
        public ?string $token = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $baseUrl = Config::string($config, ['baseUrl', 'base_url']);
        if ($baseUrl === null) {
            throw new \InvalidArgumentException('Flink config requires a non-empty "baseUrl".');
        }

        return new self(
            baseUrl: rtrim($baseUrl, '/'),
            token: Config::string($config, ['token']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }
}
