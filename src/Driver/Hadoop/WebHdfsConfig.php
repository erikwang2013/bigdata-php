<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hadoop;

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
        if (!isset($config['host']) || $config['host'] === '') {
            throw new \InvalidArgumentException('WebHDFS config requires a non-empty "host".');
        }

        return new self(
            host: (string) $config['host'],
            port: (int) ($config['port'] ?? 9870),
            scheme: (string) ($config['scheme'] ?? 'http'),
            user: (string) ($config['user'] ?? 'php'),
            delegationToken: isset($config['delegationToken'])
                ? (string) $config['delegationToken']
                : (isset($config['delegation_token']) ? (string) $config['delegation_token'] : null),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            timeout: (float) ($config['timeout'] ?? 30.0),
        );
    }

    public function baseUri(): string
    {
        return sprintf('%s://%s:%d/webhdfs/v1', $this->scheme, $this->host, $this->port);
    }
}
