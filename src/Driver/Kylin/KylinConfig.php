<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Kylin;

use Erikwang2013\BigData\Support\Config;

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
            if (Config::string($config, [$key]) === null) {
                throw new \InvalidArgumentException(sprintf('Kylin config requires a non-empty "%s".', $key));
            }
        }

        return new self(
            host: Config::string($config, ['host']),
            username: Config::string($config, ['username']),
            password: Config::string($config, ['password']),
            port: Config::int($config, ['port'], 7070),
            scheme: Config::string($config, ['scheme'], 'http'),
            project: Config::string($config, ['project']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
            basePath: '/' . trim(Config::string($config, ['base_path'], '/kylin'), '/'),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
