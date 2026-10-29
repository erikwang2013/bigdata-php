# Iceberg (REST Catalog)

A small PHP client for the [Apache Iceberg REST Catalog](https://iceberg.apache.org/spec/#rest-catalog)
API.

**Metadata only.** This driver talks to the catalog: namespaces, tables, schemas
and snapshot metadata. It never reads or writes Iceberg data files (or
manifests) itself.

| | |
|---|---|
| Driver name | `iceberg` |
| Client | `Erikwang2013\BigData\Driver\Iceberg\RestCatalogClient` |
| Config | `Erikwang2013\BigData\Driver\Iceberg\RestCatalogConfig` |
| Optional dependency | none |

## Quick start

```php
use Erikwang2013\BigData\BigData;

$catalog = BigData::driver('iceberg', [
    'base_url' => 'https://catalog.example.com',
    'warehouse' => 's3://bucket/warehouse',
    'token' => 'my-bearer-token',
]);

foreach ($catalog->namespaces() as $namespace) {
    echo implode('.', $namespace), PHP_EOL;
}
```

Direct instantiation:

```php
use Erikwang2013\BigData\Driver\Iceberg\RestCatalogClient;
use Erikwang2013\BigData\Driver\Iceberg\RestCatalogConfig;

$catalog = new RestCatalogClient(RestCatalogConfig::fromArray([
    'base_url' => 'https://catalog.example.com',
    'token' => 'my-bearer-token',
]));
// Optional HTTP client and PSR-3 logger: new RestCatalogClient($config, $http, $logger)
```

## Configuration

`RestCatalogConfig::fromArray()` accepts:

| Key             | Default   | Description                                                        |
|-----------------|-----------|--------------------------------------------------------------------|
| `base_url`      | required  | Catalog base URL; `/v1` is appended to it.                          |
| `token`         | `null`    | Bearer token.                                                      |
| `client_id`     | `null`    | OAuth client id, used when `token` is not set.                     |
| `client_secret` | `null`    | OAuth client secret, used when `token` is not set.                 |
| `scope`         | `catalog` | OAuth scope.                                                       |
| `warehouse`     | `null`    | Warehouse location sent to `/v1/config`.                           |
| `prefix`        | `null`    | Catalog prefix, inserted between `/v1` and the resource path.      |
| `verify_ssl`    | `true`    | Verify TLS certificates.                                           |
| `timeout`       | `30.0`    | Request timeout in seconds.                                        |

The config can also be constructed directly with camelCase properties.

## Catalog operations

```php
$catalog->config();          // ['defaults' => [...], 'overrides' => [...]]

$catalog->namespaces();             // [['analytics'], ['raw'], ...]
$catalog->namespaces('parent_ns');  // child namespaces
$catalog->createNamespace('analytics', ['owner' => 'data-team']);
$catalog->loadNamespace('analytics');
$catalog->dropNamespace('analytics');

$catalog->tables('analytics');      // [['namespace' => ['analytics'], 'name' => 'events'], ...]
$catalog->createTable('analytics', 'events', $schema, ['owner' => 'data-team']);

$table = $catalog->loadTable('analytics', 'events');
$table->location();          // s3://bucket/warehouse/analytics/events
$table->schema();            // Iceberg schema struct
$table->partitionSpec();     // partition fields
$table->snapshots();         // snapshot list
$table->properties();        // table properties
$table->currentSnapshotId(); // null when the table has no current snapshot

$catalog->tableExists('analytics', 'events'); // HEAD request, false on 404
$catalog->dropTable('analytics', 'events');
```

List requests send `pageSize` and follow the catalog's next page token
(`next-page-token`, also accepting `nextPageToken`) until the catalog stops
returning one. All methods return the catalog's decoded response payloads
verbatim.

## Authentication

When `token` is set it is sent as a `Bearer` token. Otherwise, if `client_id` and
`client_secret` are set, the client requests a token from `/v1/oauth/tokens`
(`client_credentials` grant, form encoded) and reuses it until 30 seconds before
`expires_in`.

## Logging

`RestCatalogClient` accepts an optional PSR-3 logger as its third constructor
argument and defaults to `Psr\Log\NullLogger`. Debug records contain the HTTP
method and URI only — auth headers are never logged.

## Errors

All exceptions extend `Erikwang2013\BigData\Driver\Iceberg\Exception\IcebergException`:

- `ConnectionException` — transport failure (DNS, refused connection, timeout).
- `RequestException` — HTTP status >= 400 or an invalid JSON response. Exposes
  `$statusCode` and `$body` when available.

## Notes

- Metadata/catalog only: no data or manifest files are read.
- `currentSnapshotId()` normalizes Iceberg's `-1` ("no snapshot") to `null`.
- No automatic retries are performed.
