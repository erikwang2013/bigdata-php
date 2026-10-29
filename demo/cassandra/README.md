# Cassandra demo

Runs a single-node Apache Cassandra 5.0 for the `Erikwang2013\BigData\Driver\Cassandra` integration tests.

## Start

```bash
cd demo/cassandra
docker compose up -d
```

The container reports healthy once `cqlsh` can query `system.local` (about 60-90 seconds on first start).

```bash
docker compose ps
```

If `cassandra:5.0` is not available on your registry, use `cassandra:4.1` — the driver and tests work with both.

## Run the integration test

From the repository root, after `composer install` (which installs `mroosz/php-cassandra` from `require-dev`):

```bash
CASSANDRA_HOST=127.0.0.1 composer test-integration
```

Without `CASSANDRA_HOST` the integration suite is skipped.

## Stop

```bash
cd demo/cassandra
docker compose down -v
```
