<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Paimon;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds the storage selected by the config "storage" key: local (default),
 * webhdfs or s3. BigData::driver() can delegate to this factory.
 */
final class StorageFactory
{
    /** @param array<string, mixed> $config */
    public static function create(
        array $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ): PaimonStorage {
        $storage = strtolower(trim((string) ($config['storage'] ?? 'local')));

        return match ($storage) {
            'local' => new LocalStorage(),
            'webhdfs' => new WebHdfsStorage(WebHdfsConfig::fromArray($config), $http, $logger),
            's3' => S3Storage::fromConfig($config),
            default => throw new \InvalidArgumentException(sprintf(
                'The Paimon driver "storage" must be "local", "webhdfs" or "s3", got "%s".',
                $storage,
            )),
        };
    }
}
