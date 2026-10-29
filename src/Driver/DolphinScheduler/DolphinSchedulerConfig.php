<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DolphinScheduler;

use Erikwang2013\BigData\Support\Config;

final class DolphinSchedulerConfig
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        public string $password,
        public ?string $token = null,
        public bool $verifySsl = true,
        public float $timeout = 30.0,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $baseUrl = Config::string($config, ['baseUrl', 'base_url']);
        if ($baseUrl === null) {
            throw new \InvalidArgumentException('DolphinScheduler config requires a non-empty "baseUrl".');
        }
        $username = Config::string($config, ['username']);
        if ($username === null) {
            throw new \InvalidArgumentException('DolphinScheduler config requires a non-empty "username".');
        }
        $password = Config::string($config, ['password']);
        if ($password === null) {
            throw new \InvalidArgumentException('DolphinScheduler config requires a non-empty "password".');
        }

        return new self(
            baseUrl: rtrim($baseUrl, '/'),
            username: $username,
            password: $password,
            token: Config::string($config, ['token']),
            verifySsl: Config::bool($config, ['verify_ssl', 'verifySsl'], true),
            timeout: Config::float($config, ['timeout'], 30.0),
        );
    }
}
