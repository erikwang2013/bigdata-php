<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Azkaban;

use Erikwang2013\BigData\Support\Config;

final class AzkabanConfig
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        public string $password,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $baseUrl = Config::string($config, ['base_url', 'baseUrl']);
        if ($baseUrl === null) {
            throw new \InvalidArgumentException('Azkaban config requires a non-empty "base_url".');
        }
        foreach (['username', 'password'] as $key) {
            if (Config::string($config, [$key]) === null) {
                throw new \InvalidArgumentException(sprintf('Azkaban config requires a non-empty "%s".', $key));
            }
        }

        return new self(
            baseUrl: rtrim($baseUrl, '/'),
            username: Config::string($config, ['username']),
            password: Config::string($config, ['password']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }
}
