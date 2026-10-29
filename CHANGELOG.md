# Changelog

All notable changes to `erikwang2013/bigdata-php` are documented here.
Versions follow `vMAJOR.MINOR.PATCH`.

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
