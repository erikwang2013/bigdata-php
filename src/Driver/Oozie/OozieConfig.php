<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Oozie;

final class OozieConfig
{
    public function __construct(
        public string $baseUrl,
        public ?string $username = null,
        public ?string $password = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $baseUrl = $config['baseUrl'] ?? $config['base_url'] ?? '';
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('Oozie config requires a non-empty "baseUrl".');
        }

        return new self(
            baseUrl: (string) $baseUrl,
            username: isset($config['username']) ? (string) $config['username'] : null,
            password: isset($config['password']) ? (string) $config['password'] : null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }
}
