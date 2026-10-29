# Cassandra

A small PHP client for [Apache Cassandra](https://cassandra.apache.org/) that
wraps the pure-PHP [mroosz/php-cassandra](https://github.com/mroosz/php-cassandra)
driver. It supports CQL queries, prepared statements and selectable consistency
levels over native protocol v3, v4 and v5.

| | |
|---|---|
| Driver name | `cassandra` |
| Client | `Erikwang2013\BigData\Driver\Cassandra\CassandraClient` |
| Config | `Erikwang2013\BigData\Driver\Cassandra\CassandraConfig` |
| Optional dependency | `mroosz/php-cassandra` |

## Installation

```bash
composer require erikwang2013/bigdata-php
composer require mroosz/php-cassandra    # required for this driver
```

`BigData::driver('cassandra', ...)` throws
`Erikwang2013\BigData\Exception\DriverUnavailableException` when the
`Cassandra\Connection` class is missing.

The package as a whole also suggests two optional dependencies used by other
drivers, not by this one:

- `ext-pdo_mysql` — Doris / StarRocks SQL and Stream Load.
- `apache/thrift` — Hive / Spark Thrift Server.

## Quick start

```php
use Erikwang2013\BigData\BigData;
use Erikwang2013\BigData\Driver\Cassandra\Consistency;

$cassandra = BigData::driver('cassandra', [
    'contact_points' => ['127.0.0.1:9042'],
    'keyspace' => 'demo',
    'username' => 'cassandra',
    'password' => 'cassandra',
]);

$result = $cassandra->query(
    'SELECT id, name FROM users WHERE id = ?',
    [42],
    Consistency::LOCAL_QUORUM,
);

foreach ($result->rows() as $row) {
    echo $row['id'], ' ', $row['name'], PHP_EOL;
}

$cassandra->close();
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Cassandra\CassandraClient;
use Erikwang2013\BigData\Driver\Cassandra\CassandraConfig;

$cassandra = new CassandraClient(CassandraConfig::fromArray([
    'contact_points' => ['127.0.0.1:9042'],
    'keyspace' => 'demo',
]));
// Optional session and PSR-3 logger: new CassandraClient($config, $session, $logger)
```

## Configuration

`CassandraConfig::fromArray()` accepts:

| Key                | Default  | Description                                                        |
|--------------------|----------|--------------------------------------------------------------------|
| `contact_points`   | required | List of `"host:port"` entries. Port defaults to `9042` when omitted. IPv6 literals must use bracketed notation (`[::1]:9042`); unbracketed IPv6 is rejected. |
| `keyspace`         | `''`     | Keyspace to connect to; empty means no keyspace.                   |
| `username`         | `''`     | Authentication username; empty means no auth.                      |
| `password`         | `''`     | Authentication password.                                           |
| `protocol_version` | `4`      | Native protocol version: `3`, `4` or `5`; other values are rejected. |
| `connect_timeout`  | `5.0`    | Connection timeout in seconds.                                     |
| `timeout`          | `10.0`   | Request timeout in seconds.                                        |
| `tls`              | `false`  | Enable TLS to the cluster.                                         |
| `verify_ssl`       | `true`   | Verify TLS peer and peer name when `tls` is enabled.               |
| `node_selection`   | `random` | `random` or `round_robin`; controls failover order only (no token-aware routing). |

`contactPoints` / `contact_points` and the other snake_case keys are accepted;
the constructor properties are camelCase/read-only.

## Usage

```php
$cassandra->connect(): void;                 // lazy; called by query/prepare/execute
$cassandra->query(string $cql, array $params = [], int $consistency = Consistency::ONE, ?int $pageSize = null, ?string $pagingState = null): ResultSet;
$cassandra->prepare(string $cql): PreparedStatement;
$cassandra->execute(PreparedStatement $statement, array $params = [], int $consistency = Consistency::ONE, ?int $pageSize = null, ?string $pagingState = null): ResultSet;
$cassandra->close(): void;
```

`ResultSet` exposes:

```php
$result->columns();     // [['name' => 'id'], ['name' => 'name'], ...]
$result->rows();        // [['id' => 42, 'name' => 'alice'], ...] — associative rows
$result->raw();         // same rows, as returned by the underlying driver
$result->count();       // number of rows
$result->pagingState(); // opaque paging state, or null
```

`INSERT`, `UPDATE`, `DELETE`, `USE` and DDL (`CREATE`/`ALTER`/`DROP`) return an
empty `ResultSet`: the server sends no rows, so `columns()` and `rows()` are
empty and no paging state is set.

### Consistency levels

Pass one of the `Consistency` constants:

`ANY`, `ONE`, `TWO`, `THREE`, `QUORUM`, `ALL`, `LOCAL_QUORUM`, `EACH_QUORUM`,
`SERIAL`, `LOCAL_SERIAL`, `LOCAL_ONE`.

The default for `query()` / `execute()` is `Consistency::ONE`.

### Prepared statements

```php
$statement = $cassandra->prepare('INSERT INTO users (id, name) VALUES (?, ?)');
$cassandra->execute($statement, [43, 'bob'], Consistency::LOCAL_QUORUM);

$statement->query(); // original CQL
$statement->id();    // prepared id as hex, or a stable sha256 fallback
```

### Paging

Pass `pageSize` to cap a page, then feed the returned `pagingState` into the
next call. The client does not fetch pages automatically:

```php
$page = $cassandra->query('SELECT * FROM users', [], Consistency::ONE, 100);
while (($state = $page->pagingState()) !== null) {
    $page = $cassandra->query('SELECT * FROM users', [], Consistency::ONE, 100, $state);
    // consume $page->rows()
}
```

## Logging

`CassandraClient` accepts an optional PSR-3 logger as its third constructor
argument and defaults to `Psr\Log\NullLogger`. Debug records contain the
operation name and consistency level, never the CQL text or bound values.

## Errors

Cassandra keeps its own exception hierarchy: all of these are driver-specific
classes: `ConnectionException` / `RequestException` extend
`Erikwang2013\BigData\Driver\Cassandra\Exception\CassandraException`, which
extends `\RuntimeException`:

- `ConnectionException` — connection or driver-level connection failure.
- `RequestException` — query, prepare or execute failure; wraps the underlying
  driver exception in `$previous`. `$statusCode` is `null` and `$body` is empty
  for this driver.

The client connects lazily and reconnects after a driver-level connection error.

## Notes and limitations

- **Metadata-only boundary:** the lakehouse drivers (Iceberg, Hudi, Paimon,
  Delta Lake) only read metadata; Cassandra is a normal data-plane driver and
  executes CQL against the cluster.
- Namespace conflict: this driver and the `ext-cassandra` extension both use the
  `Cassandra\` namespace. Loading both at the same time can conflict; use one or
  the other.
- Paging is caller-driven: pass `$pageSize` and reuse `ResultSet::pagingState()`;
  the client never fetches subsequent pages for you.
- `node_selection` changes failover order only. php-cassandra v1.2 has no
  token-aware replica routing, so it does not reduce cross-node hops.
- No automatic retries are performed.
