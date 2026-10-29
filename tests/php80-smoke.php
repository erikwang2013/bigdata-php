<?php
declare(strict_types=1);

// Plain PHP 8.0 smoke test: runs with `composer install --no-dev`, no PHPUnit.
require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\BigData\BigData;
use Erikwang2013\BigData\Driver\Trino\TrinoClient;

$expected = [
    'trino', 'hadoop-webhdfs', 'hadoop-yarn', 'spark', 'flink-sql', 'flink-jobs',
    'kylin', 'druid', 'iceberg', 'delta-lake', 'dolphinscheduler', 'airflow',
    'azkaban', 'oozie', 'hbase', 'doris', 'starrocks', 'hive', 'hudi', 'paimon',
    'cassandra',
];

$missing = array_diff($expected, BigData::drivers());
if ($missing !== []) {
    fwrite(STDERR, 'Missing drivers: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$client = BigData::driver('trino', ['host' => 'trino.local']);
if (!$client instanceof TrinoClient) {
    fwrite(STDERR, 'Unexpected Trino client: ' . get_debug_type($client) . PHP_EOL);
    exit(1);
}

echo 'PHP80 smoke OK', PHP_EOL;
