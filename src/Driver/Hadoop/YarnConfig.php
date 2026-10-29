<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hadoop;

use Erikwang2013\BigData\Support\Config;

final class YarnConfig
{
    public function __construct(
        public string $host,
        public int $port = 8088,
        public string $scheme = 'http',
        public string $user = 'php',
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $host = Config::string($config, ['host']);
        if ($host === null) {
            throw new \InvalidArgumentException('YARN config requires a non-empty "host".');
        }

        return new self(
            host: $host,
            port: Config::int($config, ['port', 'http_port', 'httpPort'], 8088),
            scheme: Config::string($config, ['scheme'], 'http') ?? 'http',
            user: Config::string($config, ['user'], 'php') ?? 'php',
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
