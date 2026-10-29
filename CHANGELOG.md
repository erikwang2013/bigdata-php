# Changelog

All notable changes to `erikwang2013/bigdata-php` are documented here.
Versions follow `vMAJOR.MINOR.PATCH`.

## [1.2.0] - 2026-10-30

### Added

- Bilingual static project site under `site/`: responsive layout, project intro,
  feature iterations, version log, install/usage docs, per-driver docs links,
  image gallery with zoomable lightbox, pet expressions, textless pet back-to-top,
  client-side search, WeChat/Alipay donation QR modals, busuanzi counter, Three.js
  hero, 404 page, SEO metadata/sitemap/robots and social previews.
- Chinese/English SVG architecture, functional design, request lifecycle and
  lifecycle diagrams, with separate image sets per language.
- Cassandra paging (`pageSize`, `pagingState`) and prepared-statement column
  metadata; integration test covers CREATE/INSERT/UPDATE/DELETE/DROP.
- Hudi 1.0 timeline naming (`{begin}_{completion}.{action}`).
- `base_url` aliases for Airflow, Flink, DolphinScheduler and Oozie configs.
- GitHub Actions integration workflow (Trino and Cassandra service containers plus
  the Docker demo stack) and a PHP 8.0 full unit-test lane.
- Hive TCLIService generated classes moved into
  `Erikwang2013\BigData\Driver\Hive\Thrift`, removing the global class namespace
  collision.

### Tests

- 230 unit tests / 799 assertions; 12 integration tests / 16 assertions (4
  external-service skips).
- Static site audit, mobile UI test and 10 ms first-byte benchmark pass locally:
  median 0.52 ms, max 1.73 ms.

### Notes

- Framework boot smoke CI was not completed in this environment; the framework
  Manager/adapter tests run, but real Laravel/ThinkPHP/Webman/Hyperf/Yii2/Yii3
  bootstraps remain a follow-up.
- Live Docker/cluster runs were blocked locally; the integration workflow runs
  them on GitHub Actions.
- Packagist submission is still pending.

## [1.1.0] - 2026-10-29

### Added

- PHP 8.0–8.5 runtime compatibility (PHP 8.1 `readonly` syntax removed;
  PHPUnit 9.6/10.5-compatible configuration; PHP 8.0 smoke CI lane).
- Framework integrations: Laravel, ThinkPHP, Webman, Hyperf, Yii2 and Yii3,
  with a shared `Integration\Manager`, per-framework configuration templates and
  Composer discovery metadata.
- Complete README rewrite with project introduction, installation, framework
  guides, driver matrix, directory structure, and four SVG diagrams:
  architecture, functional design, request lifecycle and lifecycle.
- Per-driver documentation for all 19 driver/namespace groups.
- GitHub Actions CI (PHP 8.1–8.5 matrix plus PHP 8.0 smoke) and a tag-driven
  GitHub release workflow; MIT `LICENSE`.

### Fixed

- Cassandra DDL/DML results (Void/SchemaChange/SetKeyspace) no longer call
  `asRowsResult()`; they return an empty `ResultSet`.
- Hadoop WebHDFS 307 redirects strip both client headers and client-level auth,
  and delegation tokens are redacted from debug logs.
- Hive HTTP transport honours an explicit `scheme` (`http`/`https`) and TLS
  verification.
- Flink `openSession()` sends `properties` as `{}` when empty.
- HTTP status handling is consistent across non-redirect drivers.
- Doris/StarRocks PDO errors map to the driver exception hierarchy.
- Cassandra protocol version and IPv6 contact points are validated.
- Hudi/Paimon storage selector is case-insensitive and rejects unknown values.

### Tests

- 217 unit tests / 777 assertions; 10 integration tests / 16 assertions
  (external-service tests skip when unavailable).

## [1.0.0] - 2026-10-29

### Added

- Initial unified Composer package for 19 big-data platforms:
  Trino, Hadoop (WebHDFS + YARN), Hive / Spark Thrift Server, Spark (Livy),
  Flink (SQL Gateway + JobManager), Doris, StarRocks, Kylin, Druid,
  Iceberg (REST Catalog), Hudi, Paimon, Delta Lake (Delta Sharing),
  DolphinScheduler, Airflow, Azkaban, Oozie, HBase (Stargate), Cassandra.
- Optional driver mode: `BigData::driver()`, `BigData::drivers()`,
  `BigData::available()`.
- Optional dependencies: `ext-pdo_mysql` (Doris/StarRocks),
  `apache/thrift` (Hive), `mroosz/php-cassandra` (Cassandra).
- Metadata-only lakehouse clients for Iceberg, Hudi, Paimon and Delta Sharing.
- 200 unit tests / 744 assertions using mocks, local fixtures and fake servers.

### Notes

- No live-cluster integration tests are included in this release.
- Cassandra is an optional driver backed by `mroosz/php-cassandra`.
- Hive supports NONE and SASL PLAIN/LDAP (QOP auth); Kerberos/GSSAPI and
  binary TLS are not included.
