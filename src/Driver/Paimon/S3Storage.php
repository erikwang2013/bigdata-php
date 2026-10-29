<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Paimon;

use Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException;
use Erikwang2013\BigData\Support\Config;
use Aws\S3\S3Client;

/**
 * Read-only Paimon storage backed by S3-compatible object storage.
 *
 * Paths may be absolute ("s3://bucket/warehouse/tbl") or relative to "prefix".
 * "prefix" is prepended to every key, so a relative base path such as
 * "warehouse/tbl" with prefix "lakes" maps to "lakes/warehouse/tbl/...".
 */
final class S3Storage implements PaimonStorage
{
    /** An Aws\S3\S3Client when the SDK is installed; tests may pass a fake. */
    private object $client;

    public function __construct(object $client, private string $bucket, private string $prefix = '')
    {
        $this->client = $client;
    }

    /**
     * @param array<string, mixed> $config Driver config keys: bucket, region,
     *                                     prefix, endpoint, use_path_style.
     */
    public static function fromConfig(array $config, ?object $client = null): self
    {
        $bucket = trim((string) ($config['bucket'] ?? ''));
        if ($bucket === '') {
            throw new PaimonException('The Paimon S3 storage requires a non-empty "bucket".');
        }

        if ($client === null) {
            if (!class_exists(S3Client::class)) {
                throw new PaimonException(
                    'The Paimon S3 storage requires aws/aws-sdk-php. Run: composer require aws/aws-sdk-php',
                );
            }

            $args = ['version' => 'latest', 'region' => (string) ($config['region'] ?? 'us-east-1')];
            if (($endpoint = trim((string) ($config['endpoint'] ?? ''))) !== '') {
                $args['endpoint'] = $endpoint;
            }
            if (Config::bool($config, ['use_path_style'], false)) {
                $args['use_path_style_endpoint'] = true;
            }

            $client = new S3Client($args);
        }

        return new self($client, $bucket, trim((string) ($config['prefix'] ?? ''), '/'));
    }

    public function list(string $path): array
    {
        $prefix = $this->key($path) . '/';
        $result = $this->client->listObjectsV2([
            'Bucket' => $this->bucket,
            'Prefix' => $prefix,
            'Delimiter' => '/',
        ]);

        $names = [];
        foreach ($result['Contents'] ?? [] as $object) {
            $key = (string) ($object['Key'] ?? '');
            // Ignore the zero-byte marker S3 consoles create for "folders".
            if ($key !== $prefix && $key !== '') {
                $names[] = basename($key);
            }
        }

        sort($names);

        return $names;
    }

    public function read(string $path): string
    {
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($path),
        ]);

        return (string) ($result['Body'] ?? '');
    }

    private function key(string $path): string
    {
        if (preg_match('#^s3a?://([^/]+)(?:/(.*))?$#i', $path, $matches) === 1) {
            if ($matches[1] !== $this->bucket) {
                throw new PaimonException(sprintf(
                    'Path "%s" is in S3 bucket "%s", not the configured bucket "%s".',
                    $path,
                    $matches[1],
                    $this->bucket,
                ));
            }

            $path = $matches[2] ?? '';
        }

        $path = trim($path, '/');

        return $this->prefix === '' ? $path : $this->prefix . '/' . $path;
    }
}
