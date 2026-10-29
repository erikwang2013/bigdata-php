# DolphinScheduler

A small PHP client for the [Apache DolphinScheduler](https://dolphinscheduler.apache.org/)
3.x REST API.

| | |
|---|---|
| Driver name | `dolphinscheduler` |
| Client | `Erikwang2013\BigData\Driver\DolphinScheduler\DolphinSchedulerClient` |
| Config | `Erikwang2013\BigData\Driver\DolphinScheduler\DolphinSchedulerConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$ds = BigData::driver('dolphinscheduler', [
    'baseUrl'  => 'http://127.0.0.1:12345/dolphinscheduler',
    'username' => 'admin',
    'password' => 'dolphinscheduler123',
]);

foreach ($ds->projects()['totalList'] as $project) {
    echo $project['code'], ' ', $project['name'], PHP_EOL;
}
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\DolphinScheduler\DolphinSchedulerClient;
use Erikwang2013\BigData\Driver\DolphinScheduler\DolphinSchedulerConfig;

$ds = new DolphinSchedulerClient(DolphinSchedulerConfig::fromArray([
    'baseUrl'  => 'http://127.0.0.1:12345/dolphinscheduler',
    'username' => 'admin',
    'password' => 'dolphinscheduler123',
]));
// Optional HTTP client and PSR-3 logger: new DolphinSchedulerClient($config, $http, $logger)
```

## Configuration

`DolphinSchedulerConfig::fromArray()` accepts:

| Key          | Default  | Description                                                          |
|--------------|----------|----------------------------------------------------------------------|
| `baseUrl`    | required | API base URL **including** the context path, e.g. `.../dolphinscheduler`. |
| `username`   | required | Login user name.                                                     |
| `password`   | required | Login password.                                                      |
| `token`      | `null`   | API token; when set, it is sent in the `token` header and login is skipped. |
| `verify_ssl` | `true`   | Verify TLS certificates.                                             |
| `timeout`    | `30.0`   | Request timeout in seconds.                                          |

The array keys are snake_case; constructor properties are camelCase. A trailing
`/` is trimmed from `baseUrl`.

## Authentication

The client logs in lazily on the first API call:

1. `POST {baseUrl}/login` with the form body `userName` / `userPassword`.
2. The response envelope is checked (`code` must be `0`).
3. `data.sessionId` is cached and sent as the `sessionId` header on every later
   request.

`login()` can also be called explicitly; it returns the cached session id, so
repeated calls do not hit the server again.

When a `token` is configured, no login request is made and the `token` header is
sent instead.

## Projects and workflows

```php
$ds->projects(int $page = 1, int $size = 10): array;
$ds->processDefinitions(int $projectCode, int $page = 1, int $size = 10): array;

// Start a process definition; $params is merged into the execution body and may
// contain any ExecuteProcessRequest field (failureStrategy, startParams, ...).
$ds->startProcess(int $projectCode, int $processDefinitionCode, array $params = []): array;

$ds->stopProcess(int $projectCode, int $processInstanceId): void;

$ds->processInstances(int $projectCode, int $page = 1, int $size = 10, array $filters = []): array;
$ds->taskInstances(int $projectCode, int $processInstanceId, int $page = 1, int $size = 10): array;

// skipLineNum / limit page through the plain-text task log.
$ds->taskLog(int $taskInstanceId, int $skipLineNum = 0, int $limit = 1000): string;
```

Pagination is sent as `pageNo` / `pageSize`. `processInstances()` merges
`$filters` (e.g. `stateType`, `processDefinitionCode`, `startDate`, `endDate`)
into the query string; `taskInstances()` scopes the listing with the
`processInstanceId` query parameter.

Every JSON method returns the `data` member of the DolphinScheduler envelope —
for list endpoints that is the paged object (`totalList`, `total`, `totalPage`,
...). If a server version returns a scalar in `data` (a bare process instance id,
for example), it is returned as `['data' => $value]` so nothing is lost.

## Logs

`taskLog()` calls `GET {baseUrl}/log/{taskInstanceId}` and returns the response
body unchanged; DolphinScheduler serves the log as plain text, not JSON.

## Logging

`DolphinSchedulerClient` accepts an optional PSR-3 logger as its third
constructor argument and defaults to `Psr\Log\NullLogger`. Debug records contain
the HTTP method and URI only; credentials, form bodies and response data are
never logged.

## Errors

All exceptions extend
`Erikwang2013\BigData\Driver\DolphinScheduler\Exception\DolphinSchedulerException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — any final non-2xx response (exposes `$statusCode` and
  `$body`), an invalid JSON body, or a JSON response whose `code` is not `0`.
  Application errors use the response `msg` as the exception message.

## Notes

- Targets the DolphinScheduler 3.x API; the context path is part of `baseUrl`.
- No automatic retries are performed.
