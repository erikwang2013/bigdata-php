# bigdata-php

一个 Composer 包，19 个大数据平台驱动，按需启用。

- 单一安装：`composer require erikwang2013/bigdata-php`
- 可选驱动：HTTP 驱动开箱即用；Doris/StarRocks、Hive、Cassandra 只在真正使用时才需要额外依赖
- 每个驱动保留自己的配置类、客户端类和异常，也可以绕过管理器直接 `new`
- 湖仓四格式（Iceberg/Hudi/Paimon/Delta Sharing）只读元数据，不解析数据文件

## 安装

```bash
composer require erikwang2013/bigdata-php
```

按需安装可选依赖：

```bash
composer require apache/thrift           # Hive / Spark Thrift Server
composer require mroosz/php-cassandra    # Cassandra
# Doris / StarRocks 需要 PHP 的 pdo_mysql 扩展
```

## 快速开始

```php
use Erikwang2013\BigData\BigData;

$trino = BigData::driver('trino', [
    'host' => '127.0.0.1',
    'port' => 8080,
    'user' => 'php',
    'catalog' => 'hive',
    'schema' => 'default',
]);

foreach ($trino->query('SELECT 1 AS id')->rows() as $row) {
    echo $row[0], PHP_EOL;
}
```

调度类驱动：

```php
$airflow = BigData::driver('airflow', [
    'base_url' => 'http://127.0.0.1:8081',
    'username' => 'admin',
    'password' => 'admin',
]);

$airflow->triggerDag('daily_etl', ['date' => '2026-10-29']);
```

## 可选驱动模式

```php
BigData::drivers();    // 全部驱动名
BigData::available();  // 当前环境依赖齐全的驱动名
BigData::driver($name, $config, $http = null, $logger = null);
```

缺少可选依赖时，`BigData::driver()` 抛
`Erikwang2013\BigData\Exception\DriverUnavailableException`，并提示安装命令。
所有驱动也可以直接实例化，例如：

```php
new Erikwang2013\BigData\Driver\Trino\TrinoClient(
    Erikwang2013\BigData\Driver\Trino\TrinoConfig::fromArray(['host' => '127.0.0.1'])
);
```

## 驱动一览

| 驱动名 | 客户端 | 额外依赖 | 说明 |
|---|---|---|---|
| `trino` | `Driver\Trino\TrinoClient` | — | SQL / catalog 浏览 |
| `hadoop-webhdfs` | `Driver\Hadoop\WebHdfsClient` | — | HDFS 读写，WebHDFS REST |
| `hadoop-yarn` | `Driver\Hadoop\YarnClient` | — | YARN 应用查询/终止 |
| `spark` | `Driver\Spark\LivyClient` | — | Livy sessions/statements/batches |
| `flink-sql` | `Driver\Flink\SqlGatewayClient` | — | Flink SQL Gateway |
| `flink-jobs` | `Driver\Flink\JobClient` | — | JobManager 作业查询/取消 |
| `kylin` | `Driver\Kylin\KylinClient` | — | SQL / cube / job |
| `druid` | `Driver\Druid\DruidClient` | — | SQL / native query |
| `iceberg` | `Driver\Iceberg\RestCatalogClient` | — | REST Catalog 元数据 |
| `delta-lake` | `Driver\DeltaLake\DeltaSharingClient` | — | Delta Sharing，返回 Parquet 文件清单 |
| `dolphinscheduler` | `Driver\DolphinScheduler\DolphinSchedulerClient` | — | 项目/工作流/实例/日志 |
| `airflow` | `Driver\Airflow\AirflowClient` | — | DAG/run/task/变量 |
| `azkaban` | `Driver\Azkaban\AzkabanClient` | — | 登录/flow/执行/日志 |
| `oozie` | `Driver\Oozie\OozieClient` | — | workflow/coordinator/bundle |
| `hbase` | `Driver\HBase\HBaseClient` | — | Stargate REST，Base64 单元格 |
| `doris` | `Driver\Doris\DorisClient` | `ext-pdo_mysql` | SQL 9030 + Stream Load 8030 |
| `starrocks` | `Driver\StarRocks\StarRocksClient` | `ext-pdo_mysql` | SQL 9030 + Stream Load 8030 |
| `hive` | `Driver\Hive\HiveClient` | `apache/thrift` | HiveServer2 / Spark Thrift Server |
| `hudi` | `Driver\Hudi\HudiTable` | — | 只读 `.hoodie` 元数据，本地/WebHDFS |
| `paimon` | `Driver\Paimon\PaimonTable` | — | 只读 schema/snapshot，本地/WebHDFS |
| `cassandra` | `Driver\Cassandra\CassandraClient` | `mroosz/php-cassandra` | 包装纯 PHP 驱动，v3/v4/v5 |

Hudi / Paimon 通过 `BigData::driver()` 使用时要传 `base_path`，可选
`storage => 'local'|'webhdfs'` 和 `host/port/user`：

```php
$table = BigData::driver('hudi', ['base_path' => '/warehouse/orders']);
$latest = $table->latestCommit();
```

## 边界

- Iceberg/Hudi/Paimon/Delta 只做**元数据/目录**；查询数据请走 Trino/Spark/Flink。
- Delta Sharing 返回 Parquet 文件 URL/清单，不返回行数据。
- Hive 驱动支持 NONE 与 SASL PLAIN/LDAP（QOP `auth`），不支持 Kerberos/GSSAPI；二进制传输未内置 TLS。
- Cassandra 驱动与 `ext-cassandra` 共用 `Cassandra\` 命名空间，同时加载时可能冲突。
- 所有驱动都不做自动重试；连接/HTTP 错误分别映射到各驱动的 `ConnectionException`/`RequestException`。

## 测试

```bash
composer test              # 单元测试，不依赖真实集群
composer test-integration  # 预留的集成测试入口
```

## 目录结构

```
src/
├── BigData.php            # 驱动注册与可选依赖检查
├── Exception/
└── Driver/
    ├── Trino/
    ├── Hadoop/
    ├── ...
    └── Cassandra/
tests/
└── Driver/
```
