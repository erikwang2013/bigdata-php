# Delta Lake (Delta Sharing)

A small PHP client for the [Delta Sharing](https://github.com/delta-io/delta-sharing)
REST protocol: browse shares, schemas and tables, fetch table metadata, and read
the file manifest of a table version.

> **This driver does not read row data.** Delta Sharing returns a list of
> **Parquet file URLs** with partition values, sizes and statistics. Parsing
> those files (and turning them into rows) is intentionally out of scope — hand
> the URLs to a query engine such as Trino, Spark, DuckDB or Flink.

| | |
|---|---|
| Driver name | `delta-lake` |
| Client | `Erikwang2013\BigData\Driver\DeltaLake\DeltaSharingClient` |
| Config | `Erikwang2013\BigData\Driver\DeltaLake\DeltaSharingConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$sharing = BigData::driver('delta-lake', [
    'endpoint' => 'https://sharing.example.com/delta-sharing',
    'token' => 'my-bearer-token',
]);

$result = $sharing->query('share1', 'default', 'orders', ['limit' => 100]);

$result->protocol(); // ['minReaderVersion' => 1, ...]
$result->metadata(); // ['id' => ..., 'format' => ['provider' => 'parquet'], ...]
$result->files();    // [['url' => 'https://.../part-00000.parquet', 'size' => 1234, ...], ...]
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\DeltaLake\DeltaSharingClient;
use Erikwang2013\BigData\Driver\DeltaLake\DeltaSharingConfig;

$sharing = new DeltaSharingClient(DeltaSharingConfig::fromArray([
    'endpoint' => 'https://sharing.example.com/delta-sharing',
    'token' => 'my-bearer-token',
]));
// Optional HTTP client and PSR-3 logger: new DeltaSharingClient($config, $http, $logger)
```

## Configuration

`DeltaSharingConfig::fromArray()` accepts:

| Key          | Default  | Description                                                          |
|--------------|----------|----------------------------------------------------------------------|
| `endpoint`   | required | Sharing server base URL, e.g. `https://host/delta-sharing`. Trailing `/` is trimmed. |
| `token`      | required | Bearer token sent as `Authorization: Bearer <token>` on every request. |
| `verify_ssl` | `true`   | Verify TLS certificates.                                             |
| `timeout`    | `30.0`   | Request timeout in seconds.                                          |

## Catalogue

```php
$sharing->shares();                          // [['name' => 'share1', 'id' => ...], ...]
$sharing->schemas('share1');                 // [['name' => 'default', ...], ...]
$sharing->tables('share1', 'default');       // [['name' => 'orders', ...], ...]
$sharing->tableMetadata('share1', 'default', 'orders');
// ['id' => ..., 'format' => ['provider' => 'parquet'], 'schemaString' => ..., 'partitionColumns' => [...]]
```

Each list method returns the server's `items` array. Identifiers are URL-encoded,
so share, schema and table names containing spaces or slashes are safe.

## Querying a table

Both `query()` (POST `/shares/{share}/schemas/{schema}/tables/{table}/query`) and
`tableMetadata()` (GET `.../metadata`) use the Delta Sharing newline-delimited
JSON (NDJSON) wire format. In a query response, each line has one type:

| Line type  | Returned by          |
|------------|----------------------|
| `protocol` | `$result->protocol()` |
| `metaData` | `$result->metadata()` |
| `file`     | `$result->files()`    |

Each file entry contains at least:

| Key               | Description                                              |
|-------------------|----------------------------------------------------------|
| `url`             | Pre-signed URL of a Parquet file.                        |
| `id`              | Stable file identifier.                                  |
| `partitionValues` | Partition column values for the file.                    |
| `size`            | File size in bytes.                                      |
| `stats`           | Optional JSON string with row counts, min/max, nulls.    |

Supported query options (only the ones you pass are sent):

| Option            | Description                                              |
|-------------------|----------------------------------------------------------|
| `predicateHints`  | Array of SQL predicate strings used for file pruning.    |
| `limit`           | Maximum number of files to return (sent as `limitHint`, the protocol's field name). |
| `version`         | Read a specific table version.                           |
| `timestamp`       | Read the version as of a timestamp.                      |
| `startingVersion` | First version to include (incremental query).            |
| `endingVersion`   | Last version to include (incremental query).             |

```php
$result = $sharing->query('share1', 'default', 'orders', [
    'predicateHints' => ["date = '2026-10-29'"],
    'limit' => 500,
]);

foreach ($result->files() as $file) {
    // Download $file['url'] yourself; this package does not read Parquet.
}
```

## Delta transaction log

The driver also ships a read-only `_delta_log` reader for Delta tables on local
disk or WebHDFS. It is independent of Delta Sharing; open it directly:

```php
use Erikwang2013\BigData\Driver\DeltaLake\DeltaLog;
use Erikwang2013\BigData\Driver\DeltaLake\LocalStorage;

$log = DeltaLog::open('/warehouse/orders', new LocalStorage());

$log->versions();       // [0, 1, 2, ...] commit versions found
$log->latestVersion();  // 2, or null when there are no commits
$log->protocol();       // last protocol action
$log->metadata();       // last metaData action
$log->activeFiles();    // current add actions, sorted by path
$log->commitInfo(2);    // commitInfo for a version, or null
```

WebHDFS storage:

```php
use Erikwang2013\BigData\Driver\DeltaLake\DeltaLog;
use Erikwang2013\BigData\Driver\DeltaLake\WebHdfsConfig;
use Erikwang2013\BigData\Driver\DeltaLake\WebHdfsStorage;

$log = DeltaLog::open('/warehouse/orders', new WebHdfsStorage(
    WebHdfsConfig::fromArray([
        'host' => 'namenode.local',
        'port' => 9870,
        'user' => 'php',
    ]),
));
```

Every `NNNNNNNNNNNNNNNNNN.json` commit is replayed in version order: `protocol`
and `metaData` keep the last seen action, `add` / `remove` actions produce the
current `activeFiles()` set, and `commitInfo()` is kept per version. Checkpoints
(`*.checkpoint.parquet`) and `_last_checkpoint` are ignored, so a table whose old
commits were cleaned up may expose incomplete history. Invalid JSON or actions
raise `DeltaLakeException`. There is no `BigData::driver()` entry for this
reader; construct it directly.

## Logging

`DeltaSharingClient` accepts an optional PSR-3 logger as its third constructor
argument and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP
method and URI only — the bearer token is never logged.

## Errors

Delta Sharing HTTP errors use the shared exceptions:
`Erikwang2013\BigData\Exception\ConnectionException` for transport failures and
`Erikwang2013\BigData\Exception\RequestException` for a non-2xx status, invalid
JSON/NDJSON, an `endStreamAction.errorMessage` in a query response, or a
metadata response missing `metaData`; `RequestException` exposes public
`$statusCode` and `$body`.

`Erikwang2013\BigData\Driver\DeltaLake\Exception\DeltaLakeException` (extends
`Erikwang2013\BigData\Exception\BigDataException`) remains the base for
`_delta_log` and local / WebHDFS storage errors.

## Notes

- Returns file manifests only; row data is never read.
- A token is mandatory.
- No automatic retries are performed.
