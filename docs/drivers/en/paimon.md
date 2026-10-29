# Paimon

A small PHP client for [Apache Paimon](https://paimon.apache.org/) table
metadata: read the latest schema file and the snapshot list of a table.

**Metadata only.** Only the JSON files under `schema/` and `snapshot/` are read.
Data files, manifest lists and manifest files (Avro) are never touched. Hand
query execution to Flink, Spark, Trino or another engine.

| | |
|---|---|
| Driver name | `paimon` |
| Client | `Erikwang2013\BigData\Driver\Paimon\PaimonTable` (via `BigData::driver()`) |
| Config | array (`base_path`, `storage`, plus WebHDFS or S3 keys) |
| Optional dependency | none (S3 storage needs `aws/aws-sdk-php`) |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$table = BigData::driver('paimon', ['base_path' => '/warehouse/orders']);

$table->schema();          // latest schema JSON (fields, options, ...)
$table->options();         // $table->schema()['options'] or []
$table->snapshots();       // list of snapshot JSON objects, sorted by id
$table->latestSnapshot();  // last snapshot, or null when there is none
```

## Direct instantiation

```php
use Erikwang2013\BigData\Driver\Paimon\LocalStorage;
use Erikwang2013\BigData\Driver\Paimon\PaimonTable;

$table = PaimonTable::open('/warehouse/orders', new LocalStorage());
```

## Storage

`PaimonStorage` has two methods — `list(string $path)` and
`read(string $path)` — and three implementations: local, WebHDFS and S3.

### Local filesystem

```php
use Erikwang2013\BigData\Driver\Paimon\LocalStorage;
use Erikwang2013\BigData\Driver\Paimon\PaimonTable;

$table = PaimonTable::open('/warehouse/orders', new LocalStorage());
```

### WebHDFS

Through the unified driver:

```php
$table = BigData::driver('paimon', [
    'base_path' => '/warehouse/orders',
    'storage' => 'webhdfs',
    'host' => 'namenode.local',
    'port' => 9870,
    'user' => 'php',
]);
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Paimon\PaimonTable;
use Erikwang2013\BigData\Driver\Paimon\WebHdfsConfig;
use Erikwang2013\BigData\Driver\Paimon\WebHdfsStorage;

$storage = new WebHdfsStorage(WebHdfsConfig::fromArray([
    'host' => 'namenode.local',
    'port' => 9870,
    'user' => 'php',
]));

$table = PaimonTable::open('/warehouse/orders', $storage);
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

The WebHDFS storage follows the NameNode redirect to the DataNode manually and
does not forward client-level `auth` headers to that hop. An optional PSR-3
logger can be passed as the third constructor argument.

### S3-compatible object storage

`storage => 's3'` reads from an S3 bucket through `aws/aws-sdk-php`, which is an
optional dependency:

```bash
composer require aws/aws-sdk-php
```

Through the unified driver:

```php
$table = BigData::driver('paimon', [
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
use Erikwang2013\BigData\Driver\Paimon\PaimonTable;
use Erikwang2013\BigData\Driver\Paimon\S3Storage;

$storage = S3Storage::fromConfig([
    'bucket' => 'my-bucket',
    'region' => 'eu-west-1',
    'prefix' => 'lakes',
]);

$table = PaimonTable::open('warehouse/orders', $storage);
// An existing Aws\S3\S3Client can be injected: new S3Storage($client, 'my-bucket', 'lakes')
```

## Metadata layout

- `schema/schema-{id}` — the latest file (highest id) is parsed; `LATEST`,
  `EARLIEST` and other entries are ignored.
- `snapshot/snapshot-{id}` — every numbered file is parsed and sorted by the
  JSON `id` field.
- Invalid JSON raises `Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException`.
- A table with no schema file at all raises `PaimonException`.

## Errors

- `Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException` (extends
  `Erikwang2013\BigData\Exception\BigDataException`) — missing or invalid
  metadata JSON, or a missing S3 `bucket` / SDK.
- WebHDFS transport errors use the shared
  `Erikwang2013\BigData\Exception\ConnectionException` /
  `Erikwang2013\BigData\Exception\RequestException`.
- Errors raised by the AWS SDK on S3 reads are not wrapped.

## Notes

- Read-only; no data, manifest list or manifest file is read.
- Metadata is read once and cached per `PaimonTable` instance.
- No automatic retries are performed.
