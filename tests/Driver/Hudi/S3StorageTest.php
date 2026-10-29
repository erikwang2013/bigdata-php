<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hudi;

use Erikwang2013\BigData\Driver\Hudi\Exception\HudiException;
use Erikwang2013\BigData\Driver\Hudi\LocalStorage;
use Erikwang2013\BigData\Driver\Hudi\S3Storage;
use Erikwang2013\BigData\Driver\Hudi\StorageFactory;
use Erikwang2013\BigData\Driver\Hudi\WebHdfsStorage;
use PHPUnit\Framework\TestCase;

final class S3StorageTest extends TestCase
{
    /** @return object{listCalls: array, getCalls: array} */
    private function fakeClient(): object
    {
        return new class {
            public array $listCalls = [];

            public array $getCalls = [];

            public function listObjectsV2(array $params): \ArrayObject
            {
                $this->listCalls[] = $params;

                return new \ArrayObject(['Contents' => [
                    ['Key' => 'warehouse/tbl/.hoodie/timeline/'],
                    ['Key' => 'warehouse/tbl/.hoodie/timeline/20240101000000.commit'],
                    ['Key' => 'warehouse/tbl/.hoodie/timeline/20240101000005.commit'],
                ]]);
            }

            public function getObject(array $params): \ArrayObject
            {
                $this->getCalls[] = $params;

                return new \ArrayObject(['Body' => 'hoodie.table.name=hudi_table']);
            }
        };
    }

    public function test_list_and_read_map_s3_paths_to_bucket_keys(): void
    {
        $client = $this->fakeClient();
        $storage = new S3Storage($client, 'my-bucket', 'warehouse');

        self::assertSame(
            ['20240101000000.commit', '20240101000005.commit'],
            $storage->list('s3://my-bucket/tbl/.hoodie/timeline'),
        );
        self::assertSame(
            'hoodie.table.name=hudi_table',
            $storage->read('s3a://my-bucket/tbl/.hoodie/hoodie.properties'),
        );

        self::assertSame('my-bucket', $client->listCalls[0]['Bucket']);
        self::assertSame('warehouse/tbl/.hoodie/timeline/', $client->listCalls[0]['Prefix']);
        self::assertSame('/', $client->listCalls[0]['Delimiter']);
        self::assertSame('my-bucket', $client->getCalls[0]['Bucket']);
        self::assertSame('warehouse/tbl/.hoodie/hoodie.properties', $client->getCalls[0]['Key']);
    }

    public function test_path_in_another_bucket_is_rejected(): void
    {
        $storage = new S3Storage($this->fakeClient(), 'my-bucket');

        $this->expectException(HudiException::class);
        $this->expectExceptionMessage('other-bucket');

        $storage->read('s3://other-bucket/tbl/.hoodie/hoodie.properties');
    }

    public function test_from_config_requires_bucket(): void
    {
        $this->expectException(HudiException::class);
        $this->expectExceptionMessage('bucket');

        S3Storage::fromConfig(['region' => 'eu-west-1']);
    }

    public function test_from_config_reports_missing_aws_sdk(): void
    {
        if (class_exists(\Aws\S3\S3Client::class)) {
            self::markTestSkipped('aws/aws-sdk-php is installed.');
        }

        $this->expectException(HudiException::class);
        $this->expectExceptionMessage('aws/aws-sdk-php');

        S3Storage::fromConfig(['bucket' => 'my-bucket']);
    }

    public function test_from_config_builds_aws_client(): void
    {
        if (!class_exists(\Aws\S3\S3Client::class)) {
            self::markTestSkipped('aws/aws-sdk-php is not installed.');
        }

        self::assertInstanceOf(S3Storage::class, S3Storage::fromConfig([
            'bucket' => 'my-bucket',
            'region' => 'eu-west-1',
            'prefix' => '/warehouse/',
            'endpoint' => 'http://localhost:9000',
            'use_path_style' => true,
        ]));
    }

    public function test_storage_factory_selects_local_webhdfs_and_rejects_unknown(): void
    {
        self::assertInstanceOf(LocalStorage::class, StorageFactory::create([]));
        self::assertInstanceOf(
            WebHdfsStorage::class,
            StorageFactory::create(['storage' => ' WEBHDFS ', 'host' => 'namenode.local']),
        );

        $this->expectException(\InvalidArgumentException::class);
        StorageFactory::create(['storage' => 'gcs']);
    }
}
