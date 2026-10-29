<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Druid;

final class DruidConfig
{
    public function __construct(
        public string $host,
        public int $port = 8888,
        public string $scheme = 'http',
        public ?string $username = null,
        public ?string $password = null,
        public ?string $token = null,
        public bool $verifySsl = true,
        public float $timeout = 60.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['host']) || $config['host'] === '') {
            throw new \InvalidArgumentException('Druid config requires a non-empty "host".');
        }

        return new self(
            host: (string) $config['host'],
            port: (int) ($config['port'] ?? 8888),
            scheme: (string) ($config['scheme'] ?? 'http'),
            username: isset($config['username']) ? (string) $config['username'] : null,
            password: isset($config['password']) ? (string) $config['password'] : null,
            token: isset($config['token']) ? (string) $config['token'] : null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 60.0),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
