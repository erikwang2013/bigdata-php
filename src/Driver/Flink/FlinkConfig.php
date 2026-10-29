<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Flink;

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
        if (!isset($config['baseUrl']) || $config['baseUrl'] === '') {
            throw new \InvalidArgumentException('Flink config requires a non-empty "baseUrl".');
        }

        return new self(
            baseUrl: rtrim((string) $config['baseUrl'], '/'),
            token: isset($config['token']) ? (string) $config['token'] : null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }
}
