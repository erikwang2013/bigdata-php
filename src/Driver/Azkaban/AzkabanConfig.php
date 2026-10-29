<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Azkaban;

final class AzkabanConfig
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        public string $password,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        foreach (['base_url', 'username', 'password'] as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                throw new \InvalidArgumentException(sprintf('Azkaban config requires a non-empty "%s".', $key));
            }
        }

        return new self(
            baseUrl: rtrim((string) $config['base_url'], '/'),
            username: (string) $config['username'],
            password: (string) $config['password'],
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }
}
