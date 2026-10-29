<?php
declare(strict_types=1);

namespace Erikwang2013\BigData;

use Erikwang2013\BigData\Exception\DriverUnavailableException;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * One package, optional drivers. Drivers are created on demand; optional
 * dependencies (ext-pdo_mysql, apache/thrift, mroosz/php-cassandra) are checked
 * only when the corresponding driver is requested.
 */
final class BigData
{
    /** @return list<string> */
    public static function drivers(): array
    {
        return array_keys(self::factories());
    }

    /** @return list<string> Drivers whose optional dependencies are installed. */
    public static function available(): array
    {
        return array_values(array_filter(
            self::drivers(),
            static fn (string $name): bool => self::unavailableReason($name) === null,
        ));
    }

    /**
     * @param array<string, mixed> $config Driver-specific configuration (see README).
     */
    public static function driver(
        string $name,
        array $config = [],
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ): object {
        $factories = self::factories();

        if (!isset($factories[$name])) {
            throw new DriverUnavailableException(sprintf(
                'Unknown driver "%s". Available drivers: %s.',
                $name,
                implode(', ', self::drivers()),
            ));
        }

        if (($reason = self::unavailableReason($name)) !== null) {
            throw new DriverUnavailableException($reason);
        }

        return $factories[$name]($config, $http, $logger);
    }

    /**
     * @return array<string, callable(array<string, mixed>, ?ClientInterface, ?LoggerInterface): object>
     */
    private static function factories(): array
    {
        return [
            'trino' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Trino\TrinoClient(Driver\Trino\TrinoConfig::fromArray($c), $h, $l),
            'hadoop-webhdfs' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Hadoop\WebHdfsClient(Driver\Hadoop\WebHdfsConfig::fromArray($c), $h, $l),
            'hadoop-yarn' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Hadoop\YarnClient(Driver\Hadoop\YarnConfig::fromArray($c), $h, $l),
            'spark' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Spark\LivyClient(Driver\Spark\LivyConfig::fromArray($c), $h, $l),
            'flink-sql' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Flink\SqlGatewayClient(Driver\Flink\FlinkConfig::fromArray($c), $h, $l),
            'flink-jobs' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Flink\JobClient(Driver\Flink\FlinkConfig::fromArray($c), $h, $l),
            'kylin' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Kylin\KylinClient(Driver\Kylin\KylinConfig::fromArray($c), $h, $l),
            'druid' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Druid\DruidClient(Driver\Druid\DruidConfig::fromArray($c), $h, $l),
            'iceberg' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Iceberg\RestCatalogClient(Driver\Iceberg\RestCatalogConfig::fromArray($c), $h, $l),
            'delta-lake' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\DeltaLake\DeltaSharingClient(Driver\DeltaLake\DeltaSharingConfig::fromArray($c), $h, $l),
            'dolphinscheduler' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\DolphinScheduler\DolphinSchedulerClient(Driver\DolphinScheduler\DolphinSchedulerConfig::fromArray($c), $h, $l),
            'airflow' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Airflow\AirflowClient(Driver\Airflow\AirflowConfig::fromArray($c), $h, $l),
            'azkaban' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Azkaban\AzkabanClient(Driver\Azkaban\AzkabanConfig::fromArray($c), $h, $l),
            'oozie' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Oozie\OozieClient(Driver\Oozie\OozieConfig::fromArray($c), $h, $l),
            'hbase' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\HBase\HBaseClient(Driver\HBase\HBaseConfig::fromArray($c), $h, $l),
            'doris' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Doris\DorisClient(Driver\Doris\DorisConfig::fromArray($c), null, $h, $l),
            'starrocks' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\StarRocks\StarRocksClient(Driver\StarRocks\StarRocksConfig::fromArray($c), null, $h, $l),
            'hive' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Hive\HiveClient(Driver\Hive\HiveConfig::fromArray($c)),
            'hudi' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => self::hudi($c, $h, $l),
            'paimon' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => self::paimon($c, $h, $l),
            'cassandra' => static fn (array $c, ?ClientInterface $h, ?LoggerInterface $l): object
                => new Driver\Cassandra\CassandraClient(Driver\Cassandra\CassandraConfig::fromArray($c), null, $l),
        ];
    }

    private static function unavailableReason(string $name): ?string
    {
        return match ($name) {
            'doris', 'starrocks' => extension_loaded('pdo_mysql')
                ? null
                : 'The Doris/StarRocks driver requires ext-pdo_mysql. Install the PHP pdo_mysql extension.',
            'hive' => class_exists(\Thrift\Protocol\TBinaryProtocol::class)
                ? null
                : 'The Hive driver requires apache/thrift. Run: composer require apache/thrift',
            'cassandra' => class_exists(\Cassandra\Connection::class)
                ? null
                : 'The Cassandra driver requires mroosz/php-cassandra. Run: composer require mroosz/php-cassandra',
            default => null,
        };
    }

    /** @param array<string, mixed> $config */
    private static function hudi(array $config, ?ClientInterface $http, ?LoggerInterface $logger): Driver\Hudi\HudiTable
    {
        $basePath = $config['base_path'] ?? throw new \InvalidArgumentException('The Hudi driver requires "base_path".');
        $storage = ($config['storage'] ?? 'local') === 'webhdfs'
            ? new Driver\Hudi\WebHdfsStorage(Driver\Hudi\WebHdfsConfig::fromArray($config), $http, $logger)
            : new Driver\Hudi\LocalStorage();

        return Driver\Hudi\HudiTable::open((string) $basePath, $storage);
    }

    /** @param array<string, mixed> $config */
    private static function paimon(array $config, ?ClientInterface $http, ?LoggerInterface $logger): Driver\Paimon\PaimonTable
    {
        $basePath = $config['base_path'] ?? throw new \InvalidArgumentException('The Paimon driver requires "base_path".');
        $storage = ($config['storage'] ?? 'local') === 'webhdfs'
            ? new Driver\Paimon\WebHdfsStorage(Driver\Paimon\WebHdfsConfig::fromArray($config), $http, $logger)
            : new Driver\Paimon\LocalStorage();

        return Driver\Paimon\PaimonTable::open((string) $basePath, $storage);
    }
}
