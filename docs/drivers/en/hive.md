# Hive (HiveServer2 / Spark Thrift Server)

A small PHP client for [Apache Hive](https://hive.apache.org/) HiveServer2 over
the Thrift `TCLIService` protocol. It also works with Spark Thrift Server, which
exposes the same interface.

| | |
|---|---|
| Driver name | `hive` |
| Client | `Erikwang2013\BigData\Driver\Hive\HiveClient` |
| Config | `Erikwang2013\BigData\Driver\Hive\HiveConfig` |
| Optional dependency | `apache/thrift` (`^0.24 \|\| ^0.25`) |

## Installation

```bash
composer require erikwang2013/bigdata-php
composer require apache/thrift    # required for this driver
```

`BigData::driver('hive', ...)` throws
`Erikwang2013\BigData\Exception\DriverUnavailableException` when
`apache/thrift` is not installed.

## Quick start

```php
use Erikwang2013\BigData\BigData;

$hive = BigData::driver('hive', [
    'host' => '127.0.0.1',
    'port' => 10000,
    'username' => 'php',
    'database' => 'default',
]);

try {
    $result = $hive->execute('SELECT id, name FROM users');

    foreach ($result->rows() as $row) {
        echo $row[0], ' ', $row[1], PHP_EOL;
    }
} finally {
    $hive->close();
}
```

`execute()` runs the statement, fetches all result batches and returns a
`ResultSet`:

```php
$result->columns(); // [['name' => 'id', 'type' => 'int'], ...]
$result->rows();    // [[1, 'alice'], [2, null]]
$result->count();   // number of rows
$result->raw();     // one entry per fetched batch: ['rows' => [...], 'hasMore' => bool]
```

Null values are preserved as `null`. After the last result batch is fetched, the
transport closes the server-side operation with `CloseOperation` on the
operation handle. This is best effort: a failure while closing never turns a
completed result set into an error.

`SET` statements can be issued individually:

```php
$hive->set('hive.exec.dynamic.partition', 'true'); // runs SET hive.exec.dynamic.partition=true
```

Direct instantiation (second argument optionally injects a `HiveTransport`):

```php
use Erikwang2013\BigData\Driver\Hive\HiveClient;
use Erikwang2013\BigData\Driver\Hive\HiveConfig;

$hive = new HiveClient(HiveConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 10000,
    'username' => 'php',
]));
```

`HiveClient::withTransport()` provides the same thing as an explicit factory and
bypasses the `BigData` factory (which always builds `ThriftTransport`):

```php
use Erikwang2013\BigData\Driver\Hive\HiveClient;
use Erikwang2013\BigData\Driver\Hive\HiveConfig;

$hive = HiveClient::withTransport(
    HiveConfig::fromArray(['host' => '127.0.0.1']),
    $customTransport,
);
```

## Configuration

`HiveConfig::fromArray()` accepts:

| Key          | Default       | Description                                             |
|--------------|---------------|---------------------------------------------------------|
| `host`       | required      | HiveServer2 host.                                       |
| `port`       | `10000`       | HiveServer2 port.                                       |
| `username`   | `php`         | Session user / SASL PLAIN user.                         |
| `password`   | `null`        | SASL PLAIN password.                                    |
| `auth`       | `NONE`        | `NONE`, `NOSASL`, `PLAIN` or `LDAP`; all but NOSASL use SASL PLAIN. |
| `transport`  | `binary`      | `binary` (TSocket) or `http` (THttpClient).             |
| `httpPath`   | `/cliservice` | HTTP endpoint path.                                     |
| `scheme`     | `http`        | `http` or `https` (HTTP transport only).                |
| `database`   | `default`     | Initial database (`USE <database>` on connect).         |
| `verifySsl`  | `true`        | TLS peer verification for HTTP HTTPS and binary TLS.    |
| `tls`        | `false`       | Enable TLS on the binary transport (`TSSLSocket`).       |
| `timeout`    | `30.0`        | Connect/read/write timeout in seconds.                  |

`http_path`, `http_scheme` and `verify_ssl` are accepted as aliases; `verifySsl`
applies to both HTTP `https` and binary TLS. Construct `HiveConfig` directly if
you prefer camelCase properties.

## Authentication and transports

- **NONE** — Hive's default: SASL PLAIN over the Thrift socket with the
  configured username and an empty password.
- **PLAIN / LDAP** — SASL PLAIN with the configured username and password.
- **NOSASL** — plain `TSocket` + `TBufferedTransport` + `TBinaryProtocol`, no
  SASL handshake.
  Because `apache/thrift` ships no PHP SASL transport, this package includes
  `SaslPlainTransport`, which performs the Thrift SASL handshake
  (START/OK/COMPLETE) and message framing the same way the Java and Python
  clients do.
- **HTTP** — `THttpClient` POSTs to `httpPath` over `scheme` (`http` or
  `https`); with `auth` PLAIN/LDAP it sends HTTP Basic credentials. Set
  `scheme` to `https` so credentials are not sent in cleartext, and leave
  `verifySsl` enabled unless the server uses a self-signed certificate.

Kerberos/GSSAPI and SASL QOP other than `auth` are not implemented. Set
`tls => true` to wrap the binary transport in `TSSLSocket`; `verifySsl` controls
peer and peer-name verification for both binary TLS and HTTP `https`.

## Errors

Hive keeps its own exception hierarchy: all of these are driver-specific
classes: `ConnectionException` / `RequestException` extend
`Erikwang2013\BigData\Driver\Hive\Exception\HiveException`, which extends
`\RuntimeException`:

- `ConnectionException` — socket failure, timeout, or a failed `OpenSession`.
- `RequestException` — HiveServer2 returned `ERROR_STATUS` for a statement. It
  exposes `$sqlState` and `$errorCode` when the server provides them.

## Lower-level transport

`HiveClient` accepts an optional `HiveTransport` as its second constructor
argument and an optional PSR-3 logger as its third. The default transport is
`ThriftTransport`; `HiveTransport` has `open()`, `execute()`, `fetch()` and
`close()` and is small enough to implement for testing. Debug records cover
connect/execute/close and never contain credentials.

## Notes

- The generated `TCLIService` Thrift classes are committed under
  `src/Driver/Hive/Thrift/`, so no Thrift compiler is needed at install time.
  They are Apache-2.0 licensed, like the IDL they come from.
- Limitations: no Kerberos/GSSAPI and SASL QOP `auth` only.
- No automatic retries are performed.
