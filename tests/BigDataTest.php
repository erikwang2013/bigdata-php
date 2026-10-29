<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests;

use Erikwang2013\BigData\BigData;
use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Exception\DriverUnavailableException;
use PHPUnit\Framework\TestCase;

final class BigDataTest extends TestCase
{
    public function test_drivers_lists_all_platforms(): void
    {
        $drivers = BigData::drivers();

        foreach ([
            'trino', 'hadoop-webhdfs', 'hadoop-yarn', 'spark', 'flink-sql', 'flink-jobs',
            'kylin', 'druid', 'iceberg', 'delta-lake', 'dolphinscheduler', 'airflow',
            'azkaban', 'oozie', 'hbase', 'doris', 'starrocks', 'hive', 'hudi', 'paimon',
            'cassandra',
        ] as $name) {
            self::assertContains($name, $drivers);
        }
    }

    public function test_unknown_driver_throws(): void
    {
        $this->expectException(DriverUnavailableException::class);
        BigData::driver('nope');
    }

    public function test_trino_driver_is_created(): void
    {
        $client = BigData::driver('trino', ['host' => 'trino.local']);

        self::assertInstanceOf(TrinoClient::class, $client);
    }

    public function test_hudi_requires_base_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigData::driver('hudi');
    }

    public function test_available_is_a_subset_of_drivers(): void
    {
        $available = BigData::available();

        self::assertSame([], array_diff($available, BigData::drivers()));
        self::assertContains('trino', $available);
    }

    public function test_factories_create_each_http_or_sql_driver(): void
    {
        $configs = [
            'trino' => ['host' => 'trino.local'],
            'hadoop-webhdfs' => ['host' => 'hdfs.local'],
            'hadoop-yarn' => ['host' => 'yarn.local'],
            'spark' => ['host' => 'livy.local'],
            'flink-sql' => ['baseUrl' => 'http://flink.local:8083'],
            'flink-jobs' => ['baseUrl' => 'http://flink.local:8081'],
            'kylin' => ['host' => 'kylin.local', 'username' => 'u', 'password' => 'p'],
            'druid' => ['host' => 'druid.local'],
            'iceberg' => ['base_url' => 'http://iceberg.local'],
            'delta-lake' => ['endpoint' => 'http://delta.local', 'token' => 't'],
            'dolphinscheduler' => ['baseUrl' => 'http://ds.local/dolphinscheduler', 'username' => 'u', 'password' => 'p'],
            'airflow' => ['baseUrl' => 'http://airflow.local'],
            'azkaban' => ['base_url' => 'http://azkaban.local', 'username' => 'u', 'password' => 'p'],
            'oozie' => ['baseUrl' => 'http://oozie.local/oozie'],
            'hbase' => ['baseUrl' => 'http://hbase.local'],
            'doris' => ['host' => 'doris.local', 'database' => 'db'],
            'starrocks' => ['host' => 'starrocks.local', 'database' => 'db'],
            'hive' => ['host' => 'hive.local'],
        ];

        foreach ($configs as $name => $config) {
            if (in_array($name, ['doris', 'starrocks'], true) && !extension_loaded('pdo_mysql')) {
                continue;
            }
            if ($name === 'hive' && !class_exists(\Thrift\Protocol\TBinaryProtocol::class)) {
                continue;
            }

            self::assertIsObject(BigData::driver($name, $config), $name);
        }
    }

    public function test_hudi_rejects_unknown_storage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigData::driver('hudi', ['base_path' => '/tmp/table', 'storage' => 's3']);
    }

    public function test_storage_selector_is_case_insensitive(): void
    {
        $client = BigData::driver('paimon', [
            'base_path' => __DIR__ . '/Driver/Paimon/Fixtures/table',
            'storage' => ' LOCAL ',
        ]);

        self::assertInstanceOf(\Erikwang2013\BigData\Driver\Paimon\PaimonTable::class, $client);
    }

    public function test_cassandra_reports_missing_optional_dependency(): void
    {
        if (class_exists(\Cassandra\Connection::class)) {
            self::markTestSkipped('mroosz/php-cassandra is installed.');
        }

        $this->expectException(DriverUnavailableException::class);
        BigData::driver('cassandra', ['contact_points' => ['127.0.0.1:9042']]);
    }
}
