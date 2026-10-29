<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Doris;

final class DorisConfig
{
    public function __construct(
        public string $host,
        public string $database,
        public int $port = 9030,
        public int $httpPort = 8030,
        public string $scheme = 'http',
        public string $username = 'root',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['host']) || $config['host'] === '') {
            throw new \InvalidArgumentException('Doris config requires a non-empty "host".');
        }
        if (!isset($config['database']) || $config['database'] === '') {
            throw new \InvalidArgumentException('Doris config requires a non-empty "database".');
        }

        return new self(
            host: (string) $config['host'],
            database: (string) $config['database'],
            port: (int) ($config['port'] ?? 9030),
            httpPort: (int) ($config['http_port'] ?? 8030),
            scheme: (string) ($config['scheme'] ?? 'http'),
            username: (string) ($config['username'] ?? 'root'),
            password: (string) ($config['password'] ?? ''),
            charset: (string) ($config['charset'] ?? 'utf8mb4'),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );
    }
}
