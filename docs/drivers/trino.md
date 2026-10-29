# Trino

A small PHP client for [Trino](https://trino.io/) over its HTTP statement protocol.

| | |
|---|---|
| Driver name | `trino` |
| Client | `Erikwang2013\BigData\Driver\Trino\TrinoClient` |
| Config | `Erikwang2013\BigData\Driver\Trino\TrinoConfig` |
| Optional dependency | none |

## Quick start

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

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Driver\Trino\TrinoConfig;

$client = new TrinoClient(TrinoConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 8080,
    'user' => 'php',
]));
// Optional HTTP client and PSR-3 logger: new TrinoClient($config, $http, $logger)
```

## Configuration

`TrinoConfig::fromArray()` accepts:

| Key          | Default   | Description                                                        |
|--------------|-----------|--------------------------------------------------------------------|
| `host`       | required  | Trino coordinator host.                                            |
| `port`       | `8080`    | Coordinator port.                                                  |
| `scheme`     | `http`    | `http` or `https`.                                                 |
| `user`       | `php`     | Value of `X-Trino-User`; falls back to `username` when omitted.    |
| `catalog`    | `null`    | Default `X-Trino-Catalog` header.                                  |
| `schema`     | `null`    | Default `X-Trino-Schema` header.                                   |
| `username`   | `null`    | Basic-auth username (and default `X-Trino-User`).                  |
| `password`   | `null`    | Basic-auth password.                                               |
| `token`      | `null`    | Bearer token; takes precedence over basic auth.                    |
| `verify_ssl` | `true`    | Verify TLS certificates.                                           |
| `timeout`    | `30.0`    | Request timeout in seconds.                                        |

The config can also be constructed directly with camelCase properties.

## Querying

`query()` follows Trino's `nextUri` pagination, concatenates all `data` rows and
returns a `ResultSet`:

```php
$result = $trino->query('SELECT * FROM hive.default.orders');

$result->columns(); // [['name' => 'id', 'type' => 'integer'], ...]
$result->rows();    // [[1, ...], [2, ...]]
$result->count();   // number of rows
$result->raw();     // raw pages returned by the coordinator
```

An `error.message` in any response page raises
`Erikwang2013\BigData\Driver\Trino\Exception\RequestException`.

To cancel a running query, pass the `nextUri` of the statement:

```php
$trino->cancel('http://127.0.0.1:8080/v1/statement/20231029_120000_00001_xxxxx/1');
```

## Metadata

```php
$trino->catalogs();                                  // ['hive', 'system', ...]
$trino->schemas('hive');                             // ['default', ...]
$trino->tables('hive', 'default');                   // ['orders', ...]
$trino->columns('hive', 'default', 'orders');        // ['id', 'customer_id', ...]
```

Identifiers are quoted, so names with mixed case or quotes are safe.

## Logging

The direct constructor accepts an optional PSR-3 logger as its third argument
(the second is a Guzzle `ClientInterface`). It defaults to `Psr\Log\NullLogger`.
Debug records contain the HTTP method and URI only — SQL and auth headers are
never logged.

## Errors

All exceptions extend `Erikwang2013\BigData\Driver\Trino\Exception\TrinoException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — HTTP status >= 400, invalid JSON, or a Trino error message.
  Exposes `$statusCode` and `$body` when available.

## Notes

- `query()` buffers all rows of the result in memory.
- No automatic retries are performed.
