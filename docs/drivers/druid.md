# Druid

A small PHP client for [Apache Druid](https://druid.apache.org/) over its HTTP SQL
and native query APIs.

| | |
|---|---|
| Driver name | `druid` |
| Client | `Erikwang2013\BigData\Driver\Druid\DruidClient` |
| Config | `Erikwang2013\BigData\Driver\Druid\DruidConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$druid = BigData::driver('druid', [
    'host' => '127.0.0.1',
    'port' => 8888,
    'username' => 'druid',
    'password' => 'secret',
]);

$result = $druid->sql('SELECT __time, COUNT(*) AS "count" FROM wikipedia GROUP BY 1');

foreach ($result->rows() as $row) {
    echo $row['__time'], ' ', $row['count'], PHP_EOL;
}
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Druid\DruidClient;
use Erikwang2013\BigData\Driver\Druid\DruidConfig;

$druid = new DruidClient(DruidConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 8888,
]));
// Optional HTTP client and PSR-3 logger: new DruidClient($config, $http, $logger)
```

## Configuration

`DruidConfig::fromArray()` accepts:

| Key          | Default  | Description                                     |
|--------------|----------|-------------------------------------------------|
| `host`       | required | Druid broker host.                              |
| `port`       | `8888`   | Broker port.                                    |
| `scheme`     | `http`   | `http` or `https`.                              |
| `username`   | `null`   | Basic-auth username.                            |
| `password`   | `null`   | Basic-auth password.                            |
| `token`      | `null`   | Bearer token; takes precedence over basic auth. |
| `verify_ssl` | `true`   | Verify TLS certificates.                        |
| `timeout`    | `60.0`   | Request timeout in seconds.                     |

## SQL

`sql()` POSTs to `/druid/v2/sql` with `resultFormat: "object"` and returns a
`ResultSet`:

```php
$result = $druid->sql('SELECT * FROM wikipedia', ['timeout' => 5000]);

$result->columns(); // ['__time', 'channel', ...] derived from the first row's keys
$result->rows();    // [['__time' => '...', 'channel' => '#en', ...], ...]
$result->count();   // number of rows
$result->raw();     // decoded response body
```

Rows are associative arrays keyed by column name. `null` and empty-string values
are preserved as-is. An empty result has no columns.

The optional `$context` array is sent as the query context (defaults to `{}`).

## Native queries

`nativeQuery()` POSTs the query array verbatim to `/druid/v2` and returns the
decoded JSON response:

```php
$results = $druid->nativeQuery([
    'queryType' => 'timeseries',
    'dataSource' => 'metrics',
    'granularity' => 'all',
    'aggregations' => [['type' => 'count', 'name' => 'rows']],
    'intervals' => ['2026-01-01/2026-02-01'],
]);
```

## Datasources

```php
$druid->datasources(); // ['wikipedia', 'metrics', ...] from GET /druid/v2/datasources
```

## Authentication

Basic auth is sent when `username` is set; Bearer auth is sent when `token` is set
and takes precedence over basic auth.

## Logging

`DruidClient` accepts an optional PSR-3 logger as its third constructor argument
and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP method and URI
only — SQL, query bodies, and auth headers are never logged.

## Errors

All exceptions extend `Erikwang2013\BigData\Driver\Druid\Exception\DruidException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — HTTP status >= 400, invalid JSON, or an unexpected SQL
  response shape. Exposes `$statusCode` and `$body` when available.

## Notes

- SQL results are fully buffered in memory.
- No automatic retries are performed.
