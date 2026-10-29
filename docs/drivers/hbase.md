# HBase

A small PHP client for [Apache HBase](https://hbase.apache.org/) over its REST API
(Stargate, the HBase REST server). It speaks JSON and handles the Base64 encoding
of row keys, columns and values for you.

Requires a running HBase REST server, usually started with:

```bash
hbase rest start -p 8080
```

| | |
|---|---|
| Driver name | `hbase` |
| Client | `Erikwang2013\BigData\Driver\HBase\HBaseClient` |
| Config | `Erikwang2013\BigData\Driver\HBase\HBaseConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$hbase = BigData::driver('hbase', [
    'baseUrl' => 'http://127.0.0.1:8080',
]);

$hbase->createTable('users', ['info']);

$hbase->put('users', 'row1', [
    'info:name' => 'alice',
    'info:email' => 'alice@example.com',
]);

$cells = $hbase->get('users', 'row1');
// ['info:name' => 'alice', 'info:email' => 'alice@example.com']

$hbase->delete('users', 'row1');
$hbase->deleteTable('users');
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\HBase\HBaseClient;
use Erikwang2013\BigData\Driver\HBase\HBaseConfig;

$hbase = new HBaseClient(HBaseConfig::fromArray([
    'baseUrl' => 'http://127.0.0.1:8080',
]));
// Optional HTTP client and PSR-3 logger: new HBaseClient($config, $http, $logger)
```

## Configuration

`HBaseConfig::fromArray()` accepts:

| Key          | Default   | Description                                              |
|--------------|-----------|----------------------------------------------------------|
| `baseUrl`    | required  | REST server URL, e.g. `http://127.0.0.1:8080`.            |
| `username`   | `null`    | Username for HTTP Basic auth.                             |
| `password`   | `null`    | Password for HTTP Basic auth.                             |
| `verify_ssl` | `true`    | Verify TLS certificates.                                  |
| `timeout`    | `30.0`    | Request timeout in seconds.                               |

`base_url` is accepted as an alias for `baseUrl`.

## API

| Method | Description |
|--------|-------------|
| `tables(): array` | List table names. |
| `createTable(string $table, array $columns): void` | Create a table; `$columns` is a list of column family names. |
| `deleteTable(string $table): void` | Delete a table and its data. |
| `get(string $table, string $row, array $columns = []): array` | Fetch a row as `column => value`; `$columns` optionally restricts to `family:qualifier` columns. |
| `put(string $table, string $row, array $cells): void` | Write `column => value` cells to a row. |
| `delete(string $table, string $row): void` | Delete a row. |
| `scan(string $table, array $options = []): array` | Create a scanner, fetch one batch and delete it. |
| `scannerCreate(string $table, array $options = []): string` | Create a scanner; returns its path (e.g. `/users/scanner/42`). |
| `scannerGet(string $scannerId, int $count = 100): array` | Fetch the next batch; `[]` once the scanner is exhausted. |
| `scannerDelete(string $scannerId): void` | Delete a scanner. |

`scan()` and `scannerGet()` return a list of rows:

```php
[
    ['key' => 'row1', 'cells' => ['info:name' => 'alice']],
    ['key' => 'row2', 'cells' => ['info:name' => 'bob']],
]
```

Scan options: `startRow`, `endRow` (row keys are encoded for you) and `limit`
(batch size requested from the scanner, default 100). A scan fetches a single
batch of up to `limit` rows; use `scannerCreate()` / `scannerGet()` to page
through larger tables.

Scanner ids returned by `scannerCreate()` include the table path, so pass the
value straight to `scannerGet()` and `scannerDelete()`.

## Errors

- Non-2xx responses throw `Erikwang2013\BigData\Exception\RequestException`
  with the status code and response body (`$statusCode`, `$body`).
- Network and timeout failures throw
  `Erikwang2013\BigData\Exception\ConnectionException`.
- Both extend `Erikwang2013\BigData\Exception\BigDataException`.

## Notes

- Requires the HBase REST (Stargate) server; it does not speak the native HBase
  RPC protocol.
- Scan pagination across batches is manual via the scanner methods.
- No automatic retries are performed.
