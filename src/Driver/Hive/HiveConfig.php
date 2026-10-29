<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive;

final class HiveConfig
{
    public function __construct(
        public string $host,
        public int $port = 10000,
        public string $username = 'php',
        public ?string $password = null,
        public string $auth = 'NONE',
        public string $transport = 'binary',
        public string $httpPath = '/cliservice',
        public string $database = 'default',
        public bool $verifySsl = true,
        public float $timeout = 30.0,
        public string $scheme = 'http',
    ) {
    }

    public static function fromArray(array $config): self
    {
        $host = $config['host'] ?? null;
        if (!is_string($host) || $host === '') {
            throw new \InvalidArgumentException('Hive config requires a non-empty "host".');
        }

        $auth = strtoupper((string) ($config['auth'] ?? 'NONE'));
        if (!in_array($auth, ['NONE', 'PLAIN', 'LDAP'], true)) {
            throw new \InvalidArgumentException('Hive config "auth" must be NONE, PLAIN or LDAP.');
        }

        $transport = strtolower((string) ($config['transport'] ?? 'binary'));
        if (!in_array($transport, ['binary', 'http'], true)) {
            throw new \InvalidArgumentException('Hive config "transport" must be binary or http.');
        }

        $scheme = strtolower((string) ($config['scheme'] ?? $config['http_scheme'] ?? 'http'));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Hive config "scheme" must be http or https.');
        }

        return new self(
            host: $host,
            port: (int) ($config['port'] ?? 10000),
            username: (string) ($config['username'] ?? 'php'),
            password: isset($config['password']) ? (string) $config['password'] : null,
            auth: $auth,
            transport: $transport,
            httpPath: (string) ($config['httpPath'] ?? $config['http_path'] ?? '/cliservice'),
            database: (string) ($config['database'] ?? 'default'),
            verifySsl: (bool) ($config['verifySsl'] ?? $config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
            scheme: $scheme,
        );
    }
}
