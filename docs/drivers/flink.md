# Flink (SQL Gateway and JobManager)

A small PHP client for the [Apache Flink](https://flink.apache.org/) SQL Gateway v3
and JobManager REST APIs.

| | |
|---|---|
| Driver names | `flink-sql`, `flink-jobs` |
| Clients | `Erikwang2013\BigData\Driver\Flink\SqlGatewayClient`, `Erikwang2013\BigData\Driver\Flink\JobClient` |
| Config | `Erikwang2013\BigData\Driver\Flink\FlinkConfig` |
| Optional dependency | none |

## SQL Gateway quick start

```php
use Erikwang2013\BigData\BigData;

$flink = BigData::driver('flink-sql', [
    'baseUrl' => 'http://127.0.0.1:8083',
]);

$session = $flink->openSession();

try {
    $operation = $flink->executeStatement($session, 'SELECT 1');

    $token = 0;
    do {
        $page = $flink->fetchResults($session, $operation, $token);
        foreach ($page['rows'] as $row) {
            echo implode(', ', $row), PHP_EOL;
        }
        $token = $page['nextToken'];
    } while (!$page['end']);
} finally {
    $flink->closeSession($session);
}
```

`fetchResults()` returns:

```php
[
    'columns' => [['name' => 'id', 'logicalType' => ['type' => 'INT']], ...],
    'rows' => [[1], [2], ...],
    'nextToken' => 1,    // null once the result is complete
    'end' => false,      // true when resultType is EOS
]
```

Pass the returned `nextToken` back to `fetchResults()` until `end` is true.

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Flink\FlinkConfig;
use Erikwang2013\BigData\Driver\Flink\SqlGatewayClient;

$flink = new SqlGatewayClient(FlinkConfig::fromArray([
    'baseUrl' => 'http://127.0.0.1:8083',
]));
// Optional HTTP client and PSR-3 logger: new SqlGatewayClient($config, $http, $logger)
```

## SQL Gateway API

```php
$flink->openSession(['execution.runtime-mode' => 'batch']);   // session handle
$flink->executeStatement($session, 'SELECT * FROM t');        // operation handle
$flink->fetchResults($session, $operation, $token);           // one result page
$flink->cancelOperation($session, $operation);
$flink->closeSession($session);
```

Sessions and operations are addressed by the handles returned by the gateway; the
client does not track them for you.

## JobManager

`JobClient` talks to the JobManager REST API, so point it at the JobManager base URL
(`http://host:8081`). If the SQL Gateway and JobManager share a host and port, one
config works for both.

```php
$jobs = BigData::driver('flink-jobs', [
    'baseUrl' => 'http://127.0.0.1:8081',
]);

$jobs->jobs();              // [['jid' => '...', 'name' => '...', 'state' => 'RUNNING'], ...]
$jobs->job('job-id');       // decoded /jobs/{id} payload
$jobs->cancel('job-id');    // PATCH /jobs/{id}?mode=cancel
```

## Configuration

`FlinkConfig::fromArray()` accepts:

| Key          | Default   | Description                                        |
|--------------|-----------|----------------------------------------------------|
| `baseUrl`    | required  | Gateway or JobManager base URL, e.g. `http://host:8083`. |
| `token`      | `null`    | Bearer token sent as `Authorization: Bearer ...`.  |
| `verify_ssl` | `true`    | Verify TLS certificates.                           |
| `timeout`    | `30.0`    | Request timeout in seconds.                        |

A trailing `/` is trimmed from `baseUrl`.

## Logging

Both clients accept an optional PSR-3 logger as their third constructor argument
and default to `Psr\Log\NullLogger`. Debug records contain the HTTP method and URI
only — statements and auth headers are never logged.

## Errors

All exceptions extend `Erikwang2013\BigData\Driver\Flink\Exception\FlinkException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — HTTP status >= 400 or invalid JSON. Exposes `$statusCode`
  and `$body` when available.

## Notes

- Session/operation handles are not cached or renewed by the client.
- The two driver names use the same config class but point at different services
  (SQL Gateway vs JobManager).
- No automatic retries are performed.
