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
| Config | array (`base_path`, `storage`, plus WebHDFS keys) |
| Optional dependency | none |

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
and two implementations.

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
(for example a missing or unreadable metadata file). WebHDFS transport errors use
the driver's `ConnectionException` / `RequestException`.

## Notes

- Read-only; data and log files are never touched.
- `properties` and `timeline` are read once and cached per `HudiTable` instance.
- No automatic retries are performed.
