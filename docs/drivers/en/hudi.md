# Hudi

A small PHP client for [Apache Hudi](https://hudi.apache.org/) table metadata:
read `hoodie.properties` and list the commit timeline of a table.

**Metadata only.** This driver never reads or writes Hudi data files (Parquet
base files or Avro log files). Use it to inspect table properties, table names
and commit instants; hand query execution to Spark, Trino, Flink or another
engine.

| | |
|---|---|
| Driver name | `hudi` |
| Client | `Erikwang2013\BigData\Driver\Hudi\HudiTable` (via `BigData::driver()`) |
| Config | array (`base_path`, `storage`, plus WebHDFS or S3 keys) |
| Optional dependency | none (S3 storage needs `aws/aws-sdk-php`) |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$table = BigData::driver('hudi', ['base_path' => '/warehouse/orders']);

$table->tableName();     // "orders", from hoodie.table.name
$table->properties();    // ['hoodie.table.name' => 'orders', 'hoodie.table.type' => 'MOR', ...]
$table->timeline();      // [['timestamp' => '20240101000000', 'action' => 'commit', 'state' => null], ...]
$table->latestCommit();  // "20240101000000"
```

The factory opens `.hoodie/hoodie.properties` and lists `.hoodie/timeline`
once, through the selected storage, and caches both in memory.

## Direct instantiation

```php
use Erikwang2013\BigData\Driver\Hudi\HudiTable;
use Erikwang2013\BigData\Driver\Hudi\LocalStorage;

$table = HudiTable::open('/warehouse/orders', new LocalStorage());
```

## Storage

`HudiStorage` has two methods — `list(string $path)` and `read(string $path)` —
and three implementations: local, WebHDFS and S3.

### Local filesystem

```php
use Erikwang2013\BigData\Driver\Hudi\HudiTable;
use Erikwang2013\BigData\Driver\Hudi\LocalStorage;

$table = HudiTable::open('/warehouse/orders', new LocalStorage());
```

### WebHDFS

Through the unified driver:

```php
$table = BigData::driver('hudi', [
    'base_path' => '/warehouse/orders',
    'storage' => 'webhdfs',
    'host' => 'namenode.local',
    'port' => 9870,
    'user' => 'php',
]);
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Hudi\HudiTable;
use Erikwang2013\BigData\Driver\Hudi\WebHdfsConfig;
use Erikwang2013\BigData\Driver\Hudi\WebHdfsStorage;

$storage = new WebHdfsStorage(WebHdfsConfig::fromArray([
    'host' => 'namenode.local',
    'port' => 9870,
    'user' => 'php',
]));

$table = HudiTable::open('/warehouse/orders', $storage);
```

`WebHdfsConfig::fromArray()` accepts:

| Key          | Default  | Description                                          |
|--------------|----------|------------------------------------------------------|
| `host`       | required | NameNode host.                                       |
| `port`       | `9870`   | NameNode HTTP port.                                  |
| `scheme`     | `http`   | `http` or `https`.                                   |
| `user`       | `php`    | Sent as the `user.name` query parameter.             |
| `verify_ssl` | `true`   | Verify TLS certificates.                             |
| `timeout`    | `30.0`   | Request timeout in seconds.                          |

`WebHdfsStorage` uses `op=LISTSTATUS` to list a directory and `op=OPEN` to read a
file, following the DataNode redirect manually. Client-level default headers and
`auth` are not forwarded to the DataNode hop. An optional PSR-3 logger can be
passed as the third constructor argument; every request (including redirect hops)
is logged at debug level with the method and URI only.

### S3-compatible object storage

`storage => 's3'` reads from an S3 bucket through `aws/aws-sdk-php`, which is an
optional dependency:

```bash
composer require aws/aws-sdk-php
```

Through the unified driver:

```php
$table = BigData::driver('hudi', [
    'base_path' => 'warehouse/orders',
    'storage' => 's3',
    'bucket' => 'my-bucket',
    'region' => 'eu-west-1',
    'prefix' => 'lakes',
]);
```

S3 keys:

| Key              | Default     | Description                                              |
|------------------|-------------|----------------------------------------------------------|
| `bucket`         | required    | Bucket name.                                             |
| `region`         | `us-east-1` | AWS region passed to the SDK.                            |
| `prefix`         | `''`        | Prefix prepended to every key.                           |
| `endpoint`       | none        | Custom endpoint for S3-compatible services.              |
| `use_path_style` | `false`     | Use path-style bucket addressing.                        |

`base_path` may be relative (resolved under `prefix`) or absolute
(`s3://bucket/warehouse/orders`); an absolute path must name the configured
bucket.

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Hudi\HudiTable;
use Erikwang2013\BigData\Driver\Hudi\S3Storage;

$storage = S3Storage::fromConfig([
    'bucket' => 'my-bucket',
    'region' => 'eu-west-1',
    'prefix' => 'lakes',
]);

$table = HudiTable::open('warehouse/orders', $storage);
// An existing Aws\S3\S3Client can be injected: new S3Storage($client, 'my-bucket', 'lakes')
```

## Timeline

Timeline entries are instant files named `{timestamp}.{action}` for completed
instants and `{timestamp}.{action}.{state}` for in-flight ones:

| File                                  | timestamp        | action       | state       |
|---------------------------------------|------------------|--------------|-------------|
| `20240101000000.commit`               | `20240101000000` | `commit`     | `null`      |
| `20240101000000.commit.requested`     | `20240101000000` | `commit`     | `requested` |
| `20240103000000.deltacommit.inflight` | `20240103000000` | `deltacommit` | `inflight`  |

The timeline is sorted by timestamp. `latestCommit()` ignores `requested` and
`inflight` instants and returns the highest completed timestamp, or `null` when
the table has no completed instants yet.

## Errors

Metadata problems raise `Erikwang2013\BigData\Driver\Hudi\Exception\HudiException`
(extends `Erikwang2013\BigData\Exception\BigDataException`), for example a
missing or unreadable metadata file, or a missing S3 `bucket` / SDK. WebHDFS transport errors use the shared
`Erikwang2013\BigData\Exception\ConnectionException` /
`Erikwang2013\BigData\Exception\RequestException`. Errors raised by the AWS SDK
on S3 reads are not wrapped.

## Notes

- Read-only; data and log files are never touched.
- `properties` and `timeline` are read once and cached per `HudiTable` instance.
- No automatic retries are performed.
