# Doris

A small PHP client for [Apache Doris](https://doris.apache.org/).

Doris exposes two entry points, and this driver uses both:

- **SQL** goes through the MySQL wire protocol on the FE query port (**9030** by
  default), via PDO.
- **Stream Load** goes through the FE HTTP port (**8030** by default), via a
  single `PUT` with Basic auth.

| | |
|---|---|
| Driver name | `doris` |
| Client | `Erikwang2013\BigData\Driver\Doris\DorisClient` |
| Config | `Erikwang2013\BigData\Driver\Doris\DorisConfig` |
| Optional dependency | `ext-pdo_mysql` |

## Installation

The driver ships with `erikwang2013/bigdata-php`, but SQL and configuration
require PHP's `pdo_mysql` extension:

```bash
composer require erikwang2013/bigdata-php
# enable ext-pdo_mysql in php.ini
```

`BigData::driver('doris', ...)` throws
`Erikwang2013\BigData\Exception\DriverUnavailableException` when `pdo_mysql` is
missing.

## Quick start

```php
use Erikwang2013\BigData\BigData;

$doris = BigData::driver('doris', [
    'host' => '127.0.0.1',
    'port' => 9030,
    'http_port' => 8030,
    'database' => 'demo',
    'username' => 'root',
    'password' => '',
]);

$result = $doris->query('SELECT id, name FROM orders WHERE id > ?', [100]);
foreach ($result->rows() as $row) {
    echo $row['id'], ' ', $row['name'], PHP_EOL;
}

$doris->streamLoad('orders', json_encode(['id' => 101, 'name' => 'widget']));
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Doris\DorisClient;
use Erikwang2013\BigData\Driver\Doris\DorisConfig;

$doris = new DorisClient(DorisConfig::fromArray([
    'host' => '127.0.0.1',
    'database' => 'demo',
]));
// Optional: new DorisClient($config, $pdo, $http, $logger)
```

## Configuration

`DorisConfig::fromArray()` accepts:

| Key          | Default    | Description                                     |
|--------------|------------|-------------------------------------------------|
| `host`       | required   | Doris FE host.                                  |
| `database`   | required   | Default database.                               |
| `port`       | `9030`     | FE query port (MySQL protocol).                 |
| `http_port`  | `8030`     | FE HTTP port (Stream Load).                     |
| `scheme`     | `http`     | `http` or `https` for the Stream Load endpoint. |
| `username`   | `root`     | MySQL user and Basic-auth user.                 |
| `password`   | `''`       | MySQL / Basic-auth password.                    |
| `charset`    | `utf8mb4`  | Connection charset.                             |
| `verify_ssl` | `true`     | Verify TLS certificates for Stream Load.        |
| `timeout`    | `30.0`     | Stream Load timeout in seconds.                 |

`DorisConfig::dsn()` returns
`mysql:host={host};port={port};dbname={database};charset={charset}`.

## Querying

Both methods use a prepared statement. `$params` follows PDO conventions
(positional or named).

```php
$result = $doris->query('SELECT * FROM orders');
$result->columns(); // ['id', 'name', ...] — keys of the first row
$result->rows();    // [['id' => 1, 'name' => 'widget'], ...] — PDO::FETCH_ASSOC
$result->count();   // number of rows
$result->raw();     // rows exactly as returned by PDO

$affected = $doris->execute('DELETE FROM orders WHERE id = ?', [101]);
```

`query()` returns an empty `ResultSet` (no columns, no rows) for statements that
produce no result set. PDO errors are mapped to the driver exception hierarchy:
SQLSTATE class `08` (and connection-related messages) become
`ConnectionException`; other SQL errors become `RequestException`, with the
original `\PDOException` preserved as `previous`.

PDO connections are opened lazily on the first `query()`/`execute()`, so a
Stream-Load-only client never touches the MySQL port. A `\PDO` instance can be
injected as the second constructor argument.

## Stream Load

```php
$response = $doris->streamLoad('orders', json_encode(['id' => 102, 'name' => 'gadget']), [
    'label' => 'batch_20261029_1', // default: php_<uniqid>
    'format' => 'json',            // default: json
    // any other key is sent as a Stream Load header, e.g. 'max_filter_ratio' => '0.1'
]);

echo $response['NumberLoadedRows'];
```

The request is
`PUT {scheme}://{host}:{httpPort}/api/{database}/{table}/_stream_load` with Basic
auth and the `label`, `format` and `Expect: 100-continue` headers. The decoded
response is returned as an array. A response whose `Status` is not `Success`
raises `Erikwang2013\BigData\Driver\Doris\Exception\RequestException` carrying
Doris's `Message`.

## Logging

`DorisClient` accepts an optional PSR-3 logger as its fourth constructor argument
and defaults to `Psr\Log\NullLogger`:

```php
$doris = new DorisClient($config, null, null, $logger);
```

Stream Load requests are logged at debug level (method and URI only — payload and
credentials are never logged); failures are logged at error level with the
exception in the `exception` context key.

## Errors

Stream Load errors extend `Erikwang2013\BigData\Driver\Doris\Exception\DorisException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — HTTP status >= 400, invalid JSON, or a Stream Load
  `Status` other than `Success`. Exposes `$statusCode` and `$body` when
  available.

SQL errors are also mapped: connection failures to `ConnectionException`, all
other PDO errors to `RequestException` (the original `\PDOException` is the
`previous` exception).

## Notes

- Requires `ext-pdo_mysql`; the HTTP Stream Load path alone would not, but the
  unified factory still gates the whole driver on the extension.
- Stream Load sends data as-is; it does not convert or validate payloads.
- No automatic retries are performed.
