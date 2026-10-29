<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Spark;

use Erikwang2013\BigData\Support\Config;

final class LivyConfig
{
    public function __construct(
        public string $host,
        public int $port = 8998,
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
        $host = Config::string($config, ['host']);
        if ($host === null) {
            throw new \InvalidArgumentException('Livy config requires a non-empty "host".');
        }

        return new self(
            host: $host,
            port: Config::int($config, ['port'], 8998),
            scheme: Config::string($config, ['scheme'], 'http'),
            username: Config::string($config, ['username']),
            password: Config::string($config, ['password']),
            token: Config::string($config, ['token']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 60.0),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
