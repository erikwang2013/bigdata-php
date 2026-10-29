# Spark (Livy)

A small PHP client for [Apache Spark](https://spark.apache.org/) through the
[Livy](https://livy.apache.org/) REST API — interactive SQL sessions,
statements and batches.

| | |
|---|---|
| Driver name | `spark` |
| Client | `Erikwang2013\BigData\Driver\Spark\LivyClient` |
| Config | `Erikwang2013\BigData\Driver\Spark\LivyConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$livy = BigData::driver('spark', [
    'host' => '127.0.0.1',
    'port' => 8998,
]);

// 1. Create a SQL session.
$session = $livy->createSession('sql', ['spark.executor.memory' => '1g']);
$sessionId = $session['id'];

// 2. Wait until the session is ready.
do {
    $session = $livy->session($sessionId);
} while (in_array($session['state'], ['not_started', 'starting'], true));

// 3. Run a statement and poll until the result is available.
$statement = $livy->runStatement($sessionId, 'SELECT 1 AS id');
while (($statement = $livy->statement($sessionId, $statement['id']))['state'] !== 'available') {
    usleep(500_000);
}

$rows = json_decode($statement['output']['data']['application/json'], true);

$livy->deleteSession($sessionId);
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Spark\LivyClient;
use Erikwang2013\BigData\Driver\Spark\LivyConfig;

$livy = new LivyClient(LivyConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 8998,
]));
// Optional HTTP client and PSR-3 logger: new LivyClient($config, $http, $logger)
```

Livy runs statements asynchronously: `runStatement()` returns immediately with
an `id` and `state` (`waiting`, `running`, `available`, ...), and
`statement()` returns the current snapshot. When `state` is `available` the
result lives in `output.data.application/json` (a JSON string — decode it).

## Batches

```php
$batch = $livy->createBatch([
    'file' => 'hdfs:///jobs/wordcount.jar',
    'className' => 'org.example.Main',
    'args' => ['hdfs:///input', 'hdfs:///output'],
    'conf' => ['spark.executor.memory' => '1g'],
]);

$livy->batch($batch['id']);
$livy->batches(0, 20); // from, size
$livy->deleteBatch($batch['id']);
```

## API

```php
$livy->createSession(string $kind = 'sql', array $conf = []): array;
$livy->session(int $id): array;
$livy->deleteSession(int $id): void;
$livy->runStatement(int $sessionId, string $code): array;
$livy->statement(int $sessionId, int $statementId): array;
$livy->batches(int $from = 0, int $size = 20): array;
$livy->createBatch(array $batch): array;
$livy->batch(int $id): array;
$livy->deleteBatch(int $id): void;
```

## Configuration

`LivyConfig::fromArray()` accepts:

| Key          | Default  | Description                             |
|--------------|----------|-----------------------------------------|
| `host`       | required | Livy server host.                       |
| `port`       | `8998`   | Livy server port.                       |
| `scheme`     | `http`   | `http` or `https`.                      |
| `username`   | `null`   | Basic-auth username.                    |
| `password`   | `null`   | Basic-auth password.                    |
| `token`      | `null`   | Bearer token; takes precedence over basic auth. |
| `verify_ssl` | `true`   | Verify TLS certificates.                |
| `timeout`    | `60.0`   | Request timeout in seconds.             |

Every request sends `X-Requested-By: php`, which Livy requires for CSRF
protection.

## Logging

`LivyClient` accepts an optional PSR-3 logger as its third constructor argument
and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP method and
URI only — statement code and credentials are never logged.

## Errors

Transport failures throw `Erikwang2013\BigData\Exception\ConnectionException`.
HTTP status >= 400 or invalid JSON raises
`Erikwang2013\BigData\Exception\RequestException`, which exposes public
`$statusCode` and `$body` when available. Both extend
`Erikwang2013\BigData\Exception\BigDataException`.

## Notes

- Statements and batches are asynchronous; polling is the caller's job.
- No automatic retries are performed.
