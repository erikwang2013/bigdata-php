# bigdata-php demo stack

Runs Trino, Flink 1.20 (JobManager, TaskManager, SQL Gateway) and Airflow 2.10.5
in Docker and calls each one through `erikwang2013/bigdata-php`.

## Prerequisites

- Docker 24+ with Compose v2 (`docker compose version`)
- PHP 8.0+ with the package installed (`composer install` in the repository root)
- Free host ports: 8081 (Airflow), 8083 (Flink SQL Gateway), 8084 (Flink UI), 8080 (Trino)

The stack needs roughly 4 GB of RAM and 6 GB of disk for the images.

## Start

```bash
cd demo
docker compose up -d
docker compose ps          # wait until every service is "healthy"
```

Default published ports:

| Service | Container port | Host port |
|---|---|---|
| Trino | 8080 | `TRINO_PORT` (default 8080) |
| Airflow webserver | 8080 | `AIRFLOW_PORT` (default 8081) |
| Flink SQL Gateway | 8083 | 8083 |
| Flink JobManager UI | 8081 | `FLINK_UI_PORT` (default 8084) |

If host port 8080 is already taken, publish Trino on another port and tell the
script:

```bash
TRINO_PORT=8082 docker compose up -d
TRINO_PORT=8082 php trino.php
```

## Health checks

Each service has a compose healthcheck (`docker compose ps` shows `healthy`):

```bash
curl -fsS http://127.0.0.1:8080/v1/info      # Trino
curl -fsS http://127.0.0.1:8083/v1/info      # Flink SQL Gateway
curl -fsS http://127.0.0.1:8081/health       # Airflow
```

The Flink JobManager and TaskManager are checked internally:
`http://localhost:8081/overview` for the JobManager and a TCP connect to the
TaskManager RPC port 6122.

## Run the demos

```bash
cd demo
php trino.php     # SELECT 1 -> 1
php flink.php     # opens a SQL Gateway session, runs SELECT 1 in batch mode
php airflow.php   # lists DAGs (empty when AIRFLOW__CORE__LOAD_EXAMPLES=false)
```

Airflow is created with user `admin` / password `admin`. The webserver is at
<http://127.0.0.1:8081>. The first start takes one to three minutes (database
migration, scheduler and triggerer startup).

## Stop

```bash
docker compose down
```

## Known limitations

- **No persistence.** Trino and Flink keep state in the containers, and Airflow
  uses its default SQLite database; `docker compose down` discards everything.
  Add named volumes if you need the stack to survive restarts.
- **Trino has no connectors.** The stock image exposes only the `system`
  catalog, which is enough for `SELECT 1` but not for querying files or Hive.
- **Flink runs in batch mode.** `flink.php` opens the session with
  `execution.runtime-mode: batch` so `SELECT 1` is a finite job; streaming SQL
  would not finish and the result page would never reach EOS.
- **Single-node only.** One JobManager and one TaskManager (2 slots); no
  high availability, checkpoint storage or S3/HDFS configuration.
- **Airflow standalone is for development.** It runs webserver, scheduler and
  triggerer in one container with the SequentialExecutor; do not use it for
  real workloads.
- **Docker Hub access.** If pulls fail, configure a registry mirror in the
  Docker daemon; the images are `trinodb/trino:latest`,
  `flink:1.20-scala_2.12` and `apache/airflow:2.10.5`.
