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
| Config | array (`base_path`, `storage`, plus WebHDFS keys) |
| Optional dependency | none |

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
`read(string $path)` — and two implementations.

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

## Metadata layout

- `schema/schema-{id}` — the latest file (highest id) is parsed; `LATEST`,
  `EARLIEST` and other entries are ignored.
- `snapshot/snapshot-{id}` — every numbered file is parsed and sorted by the
  JSON `id` field.
- Invalid JSON raises `Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException`.
- A table with no schema file at all raises `PaimonException`.

## Errors

- `Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException` — missing or
  invalid metadata JSON.
- WebHDFS transport errors use the driver's `ConnectionException` /
  `RequestException`.

## Notes

- Read-only; no data, manifest list or manifest file is read.
- Metadata is read once and cached per `PaimonTable` instance.
- No automatic retries are performed.
