# Oozie

A small PHP client for [Apache Oozie](https://oozie.apache.org/) over its v2 REST
API.

| | |
|---|---|
| Driver name | `oozie` |
| Client | `Erikwang2013\BigData\Driver\Oozie\OozieClient` |
| Config | `Erikwang2013\BigData\Driver\Oozie\OozieConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$oozie = BigData::driver('oozie', [
    'baseUrl' => 'http://127.0.0.1:11000/oozie',
    'username' => 'oozie',
    'password' => 'secret',
]);

$jobId = $oozie->submitWorkflow(<<<XML
<configuration>
  <property>
    <name>oozie.wf.application.path</name>
    <value>hdfs:///apps/demo/workflow.xml</value>
  </property>
</configuration>
XML);

$oozie->start($jobId);
echo $oozie->status($jobId), PHP_EOL;
```

`baseUrl` is the Oozie context root, including the `/oozie` context path.

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Oozie\OozieClient;
use Erikwang2013\BigData\Driver\Oozie\OozieConfig;

$oozie = new OozieClient(OozieConfig::fromArray([
    'baseUrl' => 'http://127.0.0.1:11000/oozie',
]));
// Optional HTTP client and PSR-3 logger: new OozieClient($config, $http, $logger)
```

## Configuration

`OozieConfig::fromArray()` accepts:

| Key          | Default   | Description                                    |
|--------------|-----------|------------------------------------------------|
| `baseUrl`    | required  | Oozie context root, e.g. `http://host:11000/oozie`. |
| `username`   | `null`    | Basic-auth username; auth is only sent when set. |
| `password`   | `null`    | Basic-auth password.                           |
| `verify_ssl` | `true`    | Verify TLS certificates.                       |
| `timeout`    | `30.0`    | Request timeout in seconds.                    |

## Submitting jobs

The body is the Oozie job **configuration** document (`<configuration>` with
`<property>` entries), not the workflow/coordinator/bundle application
definition. The application definition is referenced from the configuration; for
example a workflow job sets `oozie.wf.application.path` to the HDFS location of
the `workflow.xml`:

```php
$oozie->submitWorkflow($configurationXml);     // oozie.wf.application.path
$oozie->submitCoordinator($configurationXml);  // oozie.coord.application.path
$oozie->submitBundle($configurationXml);       // oozie.bundle.application.path
```

All three POST to `/v2/jobs` with `Content-Type: application/xml` and return the
job id from the JSON response (`{"id": "..."}`). If a proxy or older Oozie
version answers with the bare job id as plain text, the trimmed body is returned
instead.

## Inspecting jobs

```php
$oozie->job($jobId);    // GET /v2/job/{id}?show=info    (decoded JSON array)
$oozie->status($jobId); // GET /v2/job/{id}?show=status  (e.g. "RUNNING")
$oozie->log($jobId);    // GET /v2/job/{id}?show=log     (plain text)
```

`status()` parses `{"status": "..."}`; if the response is not JSON it falls back
to the trimmed plain-text body.

## Job actions

```php
$oozie->start($jobId);
$oozie->suspend($jobId);
$oozie->resume($jobId);
$oozie->kill($jobId);
```

Each sends `PUT /v2/job/{id}?action=start|suspend|resume|kill`.

`rerun()` sends a `<configuration>` XML body built from the properties array:

```php
$oozie->rerun($jobId, [
    'oozie.wf.rerun.failnodes' => 'true',
]);
```

Keys and values are XML-escaped.

## Logging

`OozieClient` accepts an optional PSR-3 logger as its third constructor argument
and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP method and
URI only — credentials and job XML are never logged.

## Errors

Transport failures throw `Erikwang2013\BigData\Exception\ConnectionException`.
Any non-2xx response (including 3xx such as 304), an Oozie error body, or
invalid JSON raises `Erikwang2013\BigData\Exception\RequestException`, which
exposes public `$statusCode` and `$body`. Both exceptions extend
`Erikwang2013\BigData\Exception\BigDataException`.

## Notes

- Uses the Oozie v2 REST API only.
- No automatic retries are performed.
