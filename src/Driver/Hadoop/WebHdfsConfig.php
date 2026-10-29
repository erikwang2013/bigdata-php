<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hadoop;

use Erikwang2013\BigData\Support\Config;

final class WebHdfsConfig
{
    public function __construct(
        public string $host,
        public int $port = 9870,
        public string $scheme = 'http',
        public string $user = 'php',
        public ?string $delegationToken = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $host = Config::string($config, ['host']);
        if ($host === null) {
            throw new \InvalidArgumentException('WebHDFS config requires a non-empty "host".');
        }

        return new self(
            host: $host,
            port: Config::int($config, ['port', 'http_port', 'httpPort'], 9870),
            scheme: Config::string($config, ['scheme'], 'http') ?? 'http',
            user: Config::string($config, ['user'], 'php') ?? 'php',
            delegationToken: Config::string($config, ['delegationToken', 'delegation_token']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d/webhdfs/v1', $this->scheme, $this->host, $this->port);
    }
}
