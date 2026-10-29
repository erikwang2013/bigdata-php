<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DolphinScheduler;

final class DolphinSchedulerConfig
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        public string $password,
        public ?string $token = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $baseUrl = $config['baseUrl'] ?? $config['base_url'] ?? '';
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('DolphinScheduler config requires a non-empty "baseUrl".');
        }
        if (!isset($config['username']) || $config['username'] === '') {
            throw new \InvalidArgumentException('DolphinScheduler config requires a non-empty "username".');
        }
        if (!isset($config['password']) || $config['password'] === '') {
            throw new \InvalidArgumentException('DolphinScheduler config requires a non-empty "password".');
        }

        return new self(
            baseUrl: rtrim((string) $baseUrl, '/'),
            username: (string) $config['username'],
            password: (string) $config['password'],
            token: isset($config['token']) ? (string) $config['token'] : null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }
}
