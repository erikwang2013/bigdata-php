# Airflow

A small PHP client for the [Apache Airflow](https://airflow.apache.org/) HTTP
REST API.

| | |
|---|---|
| Driver name | `airflow` |
| Client | `Erikwang2013\BigData\Driver\Airflow\AirflowClient` |
| Config | `Erikwang2013\BigData\Driver\Airflow\AirflowConfig` |
| Optional dependency | none |

## Airflow versions

The client defaults to the Airflow 2.x REST API base path `/api/v1`. Airflow 3.x
serves the stable API under `/api/v2`; set `api_path` accordingly:

```php
'api_path' => '/api/v2',
```

## Quick start

```php
use Erikwang2013\BigData\BigData;

$airflow = BigData::driver('airflow', [
    'baseUrl' => 'http://127.0.0.1:8080',
    'username' => 'airflow',
    'password' => 'airflow',
]);

foreach ($airflow->dags()['dags'] as $dag) {
    echo $dag['dag_id'], PHP_EOL;
}
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Airflow\AirflowClient;
use Erikwang2013\BigData\Driver\Airflow\AirflowConfig;

$airflow = new AirflowClient(AirflowConfig::fromArray([
    'baseUrl' => 'http://127.0.0.1:8080',
    'username' => 'airflow',
    'password' => 'airflow',
]));
// Optional HTTP client and PSR-3 logger: new AirflowClient($config, $http, $logger)
```

A bearer token (e.g. from a JWT-auth setup) is used instead of basic auth:

```php
'baseUrl' => 'https://airflow.example.com',
'token' => 'eyJhbGciOi...',
```

## Configuration

`AirflowConfig::fromArray()` accepts:

| Key          | Default   | Description                                            |
|--------------|-----------|--------------------------------------------------------|
| `baseUrl`    | required  | Airflow webserver base URL (no API path).              |
| `username`   | `null`    | Basic-auth username.                                   |
| `password`   | `null`    | Basic-auth password.                                   |
| `token`      | `null`    | Bearer token; takes precedence over basic auth.        |
| `api_path`   | `/api/v1` | REST API base path; use `/api/v2` for Airflow 3.x.      |
| `verify_ssl` | `true`    | Verify TLS certificates.                               |
| `timeout`    | `30.0`    | Request timeout in seconds.                            |

## API

```php
$airflow->dags(int $limit = 100, int $offset = 0);                // GET /dags
$airflow->dag(string $dagId);                                     // GET /dags/{dagId}
$airflow->triggerDag(string $dagId, array $conf = [], ?string $dagRunId = null); // POST /dags/{dagId}/dagRuns
$airflow->dagRuns(string $dagId, int $limit = 100, int $offset = 0);             // GET /dags/{dagId}/dagRuns
$airflow->dagRun(string $dagId, string $dagRunId);                // GET /dags/{dagId}/dagRuns/{runId}
$airflow->taskInstances(string $dagId, string $dagRunId);         // GET /dags/{dagId}/dagRuns/{runId}/taskInstances
$airflow->taskLog(string $dagId, string $dagRunId, string $taskId, int $tryNumber = 1); // GET .../taskInstances/{taskId}/logs/{tryNumber}
$airflow->variables(int $limit = 100, int $offset = 0);           // GET /variables
$airflow->setVariable(string $key, string $value);                // POST /variables
$airflow->deleteVariable(string $key);                            // DELETE /variables/{key}
```

JSON endpoints return the decoded response body as an array. `taskLog()` returns
the raw log text (`Accept: text/plain`).

## Logging

`AirflowClient` accepts an optional PSR-3 logger as its third constructor argument
and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP method and
URI only — credentials are never logged.

## Errors

All exceptions extend `Erikwang2013\BigData\Driver\Airflow\Exception\AirflowException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — HTTP status >= 400, invalid JSON, or an unreadable
  response. Exposes `$statusCode` and `$body` when available; a 404 is reported
  the same way.

## Notes

- `baseUrl` must not include the API path; use the `api_path` option for that.
- Pagination is limited to the `limit`/`offset` arguments exposed by each method.
- No automatic retries are performed.
