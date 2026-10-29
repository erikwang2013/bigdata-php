<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DeltaLake;

final class DeltaSharingConfig
{
    public function __construct(
        public string $endpoint,
        public string $token,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['endpoint']) || $config['endpoint'] === '') {
            throw new \InvalidArgumentException('Delta Sharing config requires a non-empty "endpoint".');
        }
        if (!isset($config['token']) || $config['token'] === '') {
            throw new \InvalidArgumentException('Delta Sharing config requires a non-empty "token".');
        }

        return new self(
            endpoint: rtrim((string) $config['endpoint'], '/'),
            token: (string) $config['token'],
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }
}
