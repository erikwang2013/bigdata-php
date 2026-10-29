# Azkaban

A small PHP client for the [Azkaban](https://azkaban.github.io/) workflow manager
HTTP API.

| | |
|---|---|
| Driver name | `azkaban` |
| Client | `Erikwang2013\BigData\Driver\Azkaban\AzkabanClient` |
| Config | `Erikwang2013\BigData\Driver\Azkaban\AzkabanConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$azkaban = BigData::driver('azkaban', [
    'base_url' => 'https://azkaban.example.com:8443',
    'username' => 'azkaban',
    'password' => 'secret',
]);

$exec = $azkaban->executeFlow('demo', 'etl');
echo $exec['execid'], PHP_EOL;

echo $azkaban->executionLog((string) $exec['execid']);
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Azkaban\AzkabanClient;
use Erikwang2013\BigData\Driver\Azkaban\AzkabanConfig;

$azkaban = new AzkabanClient(AzkabanConfig::fromArray([
    'base_url' => 'https://azkaban.example.com:8443',
    'username' => 'azkaban',
    'password' => 'secret',
]));
// Optional HTTP client and PSR-3 logger: new AzkabanClient($config, $http, $logger)
```

## Configuration

`AzkabanConfig::fromArray()` accepts:

| Key          | Default  | Description                            |
|--------------|----------|----------------------------------------|
| `base_url`   | required | Azkaban web server URL.                |
| `username`   | required | Azkaban username.                      |
| `password`   | required | Azkaban password.                      |
| `verify_ssl` | `true`   | Verify TLS certificates.               |
| `timeout`    | `30.0`   | Request timeout in seconds.            |

`baseUrl` is accepted as an alias of `base_url`.

## Authentication

`login()` posts the credentials to `/manager?action=login` and returns the
`session.id`. The id is cached on the client and reused; every other call logs in
automatically when no session is cached yet.

```php
$sessionId = $azkaban->login();
```

## API

```php
$azkaban->projects();                                  // GET /index?ajax=fetchuserprojects
$azkaban->flows('demo');                               // decoded fetchprojectflows payload
$azkaban->executeFlow('demo', 'etl', ['disabled' => 'a,b']);
$azkaban->execution('42');                             // decoded fetchexecflow payload
$azkaban->executionLog('42', 0, 50000);                // log text
$azkaban->cancel('42');                                // cancelFlow
```

`executeFlow()` posts `project`, `flow` and any extra `$options` as form fields.
`executionLog()` targets Azkaban 3.90.x: `GET /executor?ajax=fetchExecFlowLogs`
with `offset`/`length`; the server answers
`{"length": ..., "offset": ..., "data": "..."}` and the returned `data` string
(the log text) is what the method returns. The session id is sent as a
`session.id` query parameter on every request.

## Logging

`AzkabanClient` accepts an optional PSR-3 logger as its third constructor argument
and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP method and
URI only — credentials are never logged.

## Errors

Transport failures throw `Erikwang2013\BigData\Exception\ConnectionException`.
Any non-2xx HTTP status (including 3xx), invalid JSON, a login response without
`session.id`, an execution-log response without `data`, or an Azkaban response
carrying an `error` field raises `Erikwang2013\BigData\Exception\RequestException`,
which exposes public `$statusCode` and `$body`. Both exceptions extend
`Erikwang2013\BigData\Exception\BigDataException`.

## Notes

- The log endpoint is modelled on Azkaban 3.90.x; other versions may differ.
- No automatic retries are performed.
