<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Paimon;

use Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException;
use Erikwang2013\BigData\Driver\Paimon\LocalStorage;
use Erikwang2013\BigData\Driver\Paimon\S3Storage;
use Erikwang2013\BigData\Driver\Paimon\StorageFactory;
use Erikwang2013\BigData\Driver\Paimon\WebHdfsStorage;
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
                    ['Key' => 'warehouse/tbl/snapshot/'],
                    ['Key' => 'warehouse/tbl/snapshot/snapshot-1'],
                    ['Key' => 'warehouse/tbl/snapshot/snapshot-10'],
                ]]);
            }

            public function getObject(array $params): \ArrayObject
            {
                $this->getCalls[] = $params;

                return new \ArrayObject(['Body' => '{"id":10}']);
            }
        };
    }

    public function test_list_and_read_map_s3_paths_to_bucket_keys(): void
    {
        $client = $this->fakeClient();
        $storage = new S3Storage($client, 'my-bucket', 'warehouse');

        self::assertSame(
            ['snapshot-1', 'snapshot-10'],
            $storage->list('s3://my-bucket/tbl/snapshot'),
        );
        self::assertSame(
            '{"id":10}',
            $storage->read('s3://my-bucket/tbl/snapshot/snapshot-10'),
        );

        self::assertSame('warehouse/tbl/snapshot/', $client->listCalls[0]['Prefix']);
        self::assertSame('warehouse/tbl/snapshot/snapshot-10', $client->getCalls[0]['Key']);
    }

    public function test_path_in_another_bucket_is_rejected(): void
    {
        $storage = new S3Storage($this->fakeClient(), 'my-bucket');

        $this->expectException(PaimonException::class);
        $this->expectExceptionMessage('other-bucket');

        $storage->list('s3://other-bucket/tbl/schema');
    }

    public function test_from_config_reports_missing_aws_sdk(): void
    {
        if (class_exists(\Aws\S3\S3Client::class)) {
            self::markTestSkipped('aws/aws-sdk-php is installed.');
        }

        $this->expectException(PaimonException::class);
        $this->expectExceptionMessage('aws/aws-sdk-php');

        S3Storage::fromConfig(['bucket' => 'my-bucket']);
    }

    public function test_storage_factory_selects_local_webhdfs_and_rejects_unknown(): void
    {
        self::assertInstanceOf(LocalStorage::class, StorageFactory::create([]));
        self::assertInstanceOf(
            WebHdfsStorage::class,
            StorageFactory::create(['storage' => 'webhdfs', 'host' => 'namenode.local']),
        );

        $this->expectException(\InvalidArgumentException::class);
        StorageFactory::create(['storage' => 'gcs']);
    }
}
