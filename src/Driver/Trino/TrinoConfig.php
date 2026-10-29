<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Trino;

final class TrinoConfig
{
    public function __construct(
        public string $host,
        public int $port = 8080,
        public string $scheme = 'http',
        public string $user = 'php',
        public ?string $catalog = null,
        public ?string $schema = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $token = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['host']) || $config['host'] === '') {
            throw new \InvalidArgumentException('Trino config requires a non-empty "host".');
        }

        return new self(
            host: (string) $config['host'],
            port: (int) ($config['port'] ?? 8080),
            scheme: (string) ($config['scheme'] ?? 'http'),
            // X-Trino-User follows the basic-auth identity unless "user" is set explicitly.
            user: (string) ($config['user'] ?? $config['username'] ?? 'php'),
            catalog: isset($config['catalog']) ? (string) $config['catalog'] : null,
            schema: isset($config['schema']) ? (string) $config['schema'] : null,
            username: isset($config['username']) ? (string) $config['username'] : null,
            password: isset($config['password']) ? (string) $config['password'] : null,
            token: isset($config['token']) ? (string) $config['token'] : null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
