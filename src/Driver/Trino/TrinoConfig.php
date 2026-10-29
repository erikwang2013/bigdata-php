<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Trino;

use Erikwang2013\BigData\Support\Config;

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
        $host = Config::string($config, ['host']);
        if ($host === null) {
            throw new \InvalidArgumentException('Trino config requires a non-empty "host".');
        }

        return new self(
            host: $host,
            port: Config::int($config, ['port'], 8080),
            scheme: Config::string($config, ['scheme'], 'http'),
            // X-Trino-User follows the basic-auth identity unless "user" is set explicitly.
            user: Config::string($config, ['user', 'username'], 'php'),
            catalog: Config::string($config, ['catalog']),
            schema: Config::string($config, ['schema']),
            username: Config::string($config, ['username']),
            password: Config::string($config, ['password']),
            token: Config::string($config, ['token']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
