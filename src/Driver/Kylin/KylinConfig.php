<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Kylin;

final class KylinConfig
{
    public function __construct(
        public string $host,
        public string $username,
        public string $password,
        public int $port = 7070,
        public string $scheme = 'http',
        public ?string $project = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
        public string $basePath = '/kylin',
    ) {
    }

    public static function fromArray(array $config): self
    {
        foreach (['host', 'username', 'password'] as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                throw new \InvalidArgumentException(sprintf('Kylin config requires a non-empty "%s".', $key));
            }
        }

        return new self(
            host: (string) $config['host'],
            username: (string) $config['username'],
            password: (string) $config['password'],
            port: (int) ($config['port'] ?? 7070),
            scheme: (string) ($config['scheme'] ?? 'http'),
            project: isset($config['project']) ? (string) $config['project'] : null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
            basePath: '/' . trim((string) ($config['base_path'] ?? '/kylin'), '/'),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
