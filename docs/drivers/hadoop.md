# Hadoop (WebHDFS and YARN)

A small PHP client for [Hadoop](https://hadoop.apache.org/) WebHDFS and YARN REST APIs.

| | |
|---|---|
| Driver names | `hadoop-webhdfs`, `hadoop-yarn` |
| Clients | `Erikwang2013\BigData\Driver\Hadoop\WebHdfsClient`, `Erikwang2013\BigData\Driver\Hadoop\YarnClient` |
| Configs | `Erikwang2013\BigData\Driver\Hadoop\WebHdfsConfig`, `Erikwang2013\BigData\Driver\Hadoop\YarnConfig` |
| Optional dependency | none |

## WebHDFS quick start

```php
use Erikwang2013\BigData\BigData;

$hdfs = BigData::driver('hadoop-webhdfs', [
    'host' => '127.0.0.1',
    'port' => 9870,
    'user' => 'php',
]);

foreach ($hdfs->listStatus('/data') as $status) {
    echo $status['pathSuffix'], ' ', $status['type'], PHP_EOL;
}

$contents = $hdfs->read('/data/events.csv');
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Hadoop\WebHdfsClient;
use Erikwang2013\BigData\Driver\Hadoop\WebHdfsConfig;

$hdfs = new WebHdfsClient(WebHdfsConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 9870,
    'user' => 'php',
]));
// Optional HTTP client and PSR-3 logger: new WebHdfsClient($config, $http, $logger)
```

### WebHDFS configuration

`WebHdfsConfig::fromArray()` accepts:

| Key                | Default  | Description                                              |
|--------------------|----------|----------------------------------------------------------|
| `host`             | required | NameNode host.                                           |
| `port`             | `9870`   | NameNode HTTP port.                                      |
| `scheme`           | `http`   | `http` or `https`.                                       |
| `user`             | `php`    | Sent as the `user.name` query parameter.                 |
| `delegationToken`  | `null`   | Sent as the `delegation` query parameter (also `delegation_token`). |
| `verify_ssl`       | `true`   | Verify TLS certificates.                                 |
| `timeout`          | `30.0`   | Request timeout in seconds.                              |

### File operations

```php
$hdfs->listStatus('/data');                 // list<array> of FileStatus entries
$hdfs->fileStatus('/data/events.csv');      // array FileStatus
$hdfs->mkdirs('/data/2026/10');             // bool
$hdfs->delete('/data/tmp', true);           // bool, recursive
$hdfs->rename('/data/a.csv', '/data/b.csv'); // bool
$hdfs->write('/data/note.txt', 'hello', true); // bool, overwrite
$hdfs->read('/data/note.txt');              // string
$hdfs->readStream('/data/note.txt');        // Psr\Http\Message\StreamInterface
```

`read()` and `write()` follow the NameNode's 307 redirect to a DataNode manually
(up to 5 hops). The redirected request carries only `user.name`/`delegation`
query parameters and never forwards `Authorization` or other caller headers.
`write()` re-sends the body on every hop.

## YARN quick start

```php
$yarn = BigData::driver('hadoop-yarn', [
    'host' => '127.0.0.1',
    'port' => 8088,
    'user' => 'php',
]);

$metrics = $yarn->clusterMetrics();          // clusterMetrics array
$apps = $yarn->apps(['state' => 'RUNNING']); // list<array> of applications
$app = $yarn->app('application_1234_0001');
$yarn->killApp('application_1234_0001');     // PUT {"state":"KILLED"}
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Hadoop\YarnClient;
use Erikwang2013\BigData\Driver\Hadoop\YarnConfig;

$yarn = new YarnClient(YarnConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 8088,
]));
```

### YARN configuration

`YarnConfig::fromArray()` accepts:

| Key          | Default  | Description                              |
|--------------|----------|------------------------------------------|
| `host`       | required | ResourceManager host.                    |
| `port`       | `8088`   | ResourceManager HTTP port.               |
| `scheme`     | `http`   | `http` or `https`.                       |
| `user`       | `php`    | Sent as the `user.name` query parameter. |
| `verify_ssl` | `true`   | Verify TLS certificates.                 |
| `timeout`    | `30.0`   | Request timeout in seconds.              |

Endpoints used: `GET /ws/v1/cluster/metrics`, `GET /ws/v1/cluster/apps`,
`GET /ws/v1/cluster/apps/{id}`, and
`PUT /ws/v1/cluster/apps/{id}/state` with `{"state":"KILLED"}`.

## Logging

Both clients accept an optional PSR-3 logger as their third constructor argument
and default to `Psr\Log\NullLogger`. Each HTTP request (including every redirect
hop) emits a debug record with the method and URI. Query strings are omitted
from logged URIs, so `user.name` and `delegation` tokens never appear in logs.

## Errors

All exceptions extend `Erikwang2013\BigData\Driver\Hadoop\Exception\HadoopException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — HTTP status >= 400, invalid JSON, or a WebHDFS
  `RemoteException`. Exposes `$statusCode` and `$body` when available; the
  `RemoteException.message` is used as the exception message.

## Notes

- WebHDFS redirects are followed manually; DataNode hops never receive the
  caller's `Authorization` header.
- No automatic retries are performed.
