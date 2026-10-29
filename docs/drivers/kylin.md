# Kylin

A small PHP client for the [Apache Kylin](https://kylin.apache.org/) REST API.

| | |
|---|---|
| Driver name | `kylin` |
| Client | `Erikwang2013\BigData\Driver\Kylin\KylinClient` |
| Config | `Erikwang2013\BigData\Driver\Kylin\KylinConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$kylin = BigData::driver('kylin', [
    'host' => '127.0.0.1',
    'port' => 7070,
    'username' => 'admin',
    'password' => 'KYLIN',
    'project' => 'learn_kylin',
]);

foreach ($kylin->query('SELECT ID FROM KYLIN_SALES LIMIT 10')->rows() as $row) {
    echo $row[0], PHP_EOL;
}
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Kylin\KylinClient;
use Erikwang2013\BigData\Driver\Kylin\KylinConfig;

$kylin = new KylinClient(KylinConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 7070,
    'username' => 'admin',
    'password' => 'KYLIN',
]));
// Optional HTTP client and PSR-3 logger: new KylinClient($config, $http, $logger)
```

## Configuration

`KylinConfig::fromArray()` accepts:

| Key          | Default   | Description                                          |
|--------------|-----------|------------------------------------------------------|
| `host`       | required  | Kylin server host.                                   |
| `port`       | `7070`    | Kylin server port.                                   |
| `scheme`     | `http`    | `http` or `https`.                                   |
| `username`   | required  | Basic-auth username.                                 |
| `password`   | required  | Basic-auth password.                                 |
| `project`    | `null`    | Default project for query and metadata calls.        |
| `verify_ssl` | `true`    | Verify TLS certificates.                             |
| `timeout`    | `30.0`    | Request timeout in seconds.                          |
| `base_path`  | `/kylin`  | Kylin web context path prepended to every API route. |

The config can also be constructed directly with camelCase properties.

## Querying

`query()` sends `POST /kylin/api/query` with `{sql, project, limit, offset}` and
returns a `ResultSet`:

```php
$result = $kylin->query(
    'SELECT ID FROM KYLIN_SALES',
    project: 'learn_kylin',
    limit: 100,
    offset: 0,
);

$result->columns(); // columnMeta entries: [['name' => 'ID', 'label' => 'ID', 'dataType' => 'integer']]
$result->rows();    // [[1], [2]]
$result->count();   // number of rows
$result->raw();     // raw responses returned by Kylin
```

When `project` is omitted the configured default is used. A response with
`isException = true` raises `Erikwang2013\BigData\Driver\Kylin\Exception\RequestException`
whose message is the server's `exceptionMessage`.

## Metadata

```php
$kylin->cubes(offset: 0, limit: 100);   // GET /kylin/api/cubes
$kylin->models(offset: 0, limit: 100);  // GET /kylin/api/models
$kylin->jobs(['status' => 'RUNNING']);  // GET /kylin/api/jobs, filters as query params
```

Each method returns the list under the response's `cubes` / `models` / `jobs`
key (or the decoded payload when the server returns a bare list). The configured
project is sent as the `project` query parameter unless a filter overrides it.

Start a cube build:

```php
$kylin->buildCube(
    'kylin_sales_cube',
    '2026-01-01 00:00:00',
    '2026-01-02 00:00:00',
    buildType: 'BUILD',
);
```

This sends `PUT /kylin/api/cubes/{cube}/rebuild` with
`{startTime, endTime, buildType}` (`buildType` defaults to `BUILD`) and returns
the decoded response.

## Logging

`KylinClient` accepts an optional PSR-3 logger as its third constructor argument
and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP method and
URI only — SQL and the Authorization header are never logged.

## Errors

All exceptions extend `Erikwang2013\BigData\Driver\Kylin\Exception\KylinException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — any non-2xx HTTP response, invalid JSON, or a Kylin
  `isException` response. Exposes `$statusCode` and `$body` when available.

## Notes

- Basic auth is mandatory (`username` and `password` are required).
- No automatic retries are performed.
