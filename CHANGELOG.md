# Changelog

All notable changes to `erikwang2013/bigdata-php` are documented here.
Versions follow `vMAJOR.MINOR.PATCH`.

## [2.1.1] - 2026-10-30

### Fixed

- Config auto-publishing writes a temporary file and renames it into place, so
  concurrent Webman/Hyperf workers can never read a half-written config.

## [2.1.0] - 2026-10-30

### Added

- Hive `auth => 'NONE'` now performs the SASL PLAIN handshake HiveServer2
  expects by default; `auth => 'NOSASL'` keeps the raw Thrift transport.
- Framework adapters publish their config template on first boot, following
  each framework's path convention: `config/bigdata.php` for Laravel, ThinkPHP,
  Webman and Yii2, and `config/autoload/bigdata.php` for Hyperf. Existing files
  are never overwritten. Yii3 keeps using yiisoft/config's automatic merge and
  copies nothing.

### Fixed

- Framework smoke CI now installs `illuminate/container` and
  `vlucas/phpdotenv`; the Laravel provider resolves `Illuminate\Container\Container`
  and `Env` directly.
- Dependabot no longer attempts Composer updates. The package supports PHP 8.0
  while dev tooling needs PHP 8.1+, so Dependabot's 8.0 platform resolution
  cannot satisfy `require-dev`. GitHub Actions updates remain automated.

### Tests

- 294 unit tests / 978 assertions; 14 integration tests / 16 assertions (6
  external-service skips).

## [2.0.0] - 2026-10-30

### Breaking

- HTTP-based drivers (Trino, Hadoop, Spark, Flink, Kylin, Druid, Iceberg,
  DolphinScheduler, Airflow, Azkaban, Oozie, HBase, and the Hudi / Paimon /
  Delta Lake HTTP paths) now throw the shared
  `Erikwang2013\BigData\Exception\ConnectionException` and `RequestException`.
  Their per-driver `Exception/*` and `Support/Http` classes were removed. Catch
  `BigDataException` or the shared classes instead.
- Driver-specific exceptions remain for Hive, Cassandra, Doris and StarRocks
  (their own hierarchy) and for Hudi/Paimon/Delta Lake metadata
  (`HudiException`, `PaimonException`, `DeltaLakeException`).

### Added

- Shared support layer: `Support\Http` (Guzzle wrapper with consistent 2xx
  handling), `Support\ResultSet`, `Support\Config`, and the
  `BigDataException` / `ConnectionException` / `RequestException` hierarchy.
- Hudi and Paimon S3-compatible storage (`storage => 's3'`) through the optional
  `aws/aws-sdk-php`, plus `StorageFactory` for local / WebHDFS / S3 selection.
- Read-only `DeltaLog` for Delta Lake `_delta_log` on local disk and WebHDFS
  (versions, protocol, metadata, active files, commit info).
- Hive binary TLS (`tls => true` via `TSSLSocket`), `scheme` / `verifySsl`
  options, `HiveClient::withTransport()`, and `CloseOperation` cleanup after an
  exhausted fetch.
- Cassandra `node_selection` (`random` / `round_robin`), explicit
  `pageSize` / `pagingState` arguments, and prepared-statement paging support.
- PHPStan level 5 and PHPUnit coverage workflows; framework boot smoke matrix
  (Laravel, ThinkPHP, Webman, Hyperf, Yii2, Yii3); HiveServer2 integration job;
  Dependabot updates.
- English driver and framework docs; committed site source, build script and
  Playwright audit.

### Changed

- Doris and StarRocks are no longer gated on `ext-pdo_mysql`: `streamLoad()`
  works without it, while `query()` / `execute()` raise a clear driver exception.
- Azkaban accepts `baseUrl` as an alias of `base_url`.
- Hadoop/HBase/Iceberg/Hudi/Paimon/Delta Lake use the shared HTTP helper while
  preserving manual WebHDFS redirects without forwarding credentials.
- PHP 8.0 full unit lane; test suite runs on PHPUnit 9.6 and 10.5.

### Fixed

- Hive Thrift timeouts are sent as milliseconds; empty Flink properties encode
  as a JSON object; HTTP status handling is consistent across drivers; Doris /
  StarRocks PDO failures map to the driver exception hierarchy.

### Tests

- 289 unit tests / 969 assertions; 14 integration tests / 16 assertions (6
  external-service skips).

## [1.2.4] - 2026-10-30

### Changed

- `site/` is now a local build artifact. It is excluded from the repository,
  future tags and GitHub release source archives. Existing v1.2.0-v1.2.3 tags
  still contain it.
- No runtime code changed.

## [1.2.3] - 2026-10-30

### Added

- Project pet **Duoduo** (`docs/pet.svg`), a teal data-octopus with eight
  colored tentacles for SQL/OLAP, Hadoop, lakehouse, scheduling and NoSQL, a
  19-platform badge, and 12 expression SVGs in `docs/pet-expressions/`.
- README section linking the pet and expression gallery.
- Site rebuilt with the new pet, expressions and v1.2.3 version log entry.

No runtime code changed.

## [1.2.2] - 2026-10-30

### Changed

- Packagist is live. Installation docs now use
  `composer require erikwang2013/bigdata-php:^1.2`; the VCS fallback is no longer
  needed.
- Site version log includes v1.2.2.

## [1.2.1] - 2026-10-30

### Fixed

- CI: the PHP 8.0 full unit lane now disables Composer's generated
  platform-check, which included resolved dev packages that need PHP 8.1+.
  No runtime code changed; v1.2.0 remains the feature release.

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
- Packagist is live; v1.2.0+ is installable from the default repository.

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
