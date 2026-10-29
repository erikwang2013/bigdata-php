# Webman

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\Webman\Bootstrap` |
| Framework floor | Webman 1.x/2.x with Workerman 4+ (PHP 8.0+) |
| Adapter PHP floor | 8.0 |
| Container binding | `Manager::class` |

## Registration

Webman has no Composer auto-discovery. Add the bootstrap class to
`config/bootstrap.php`:

```php
return [
    support\bootstrap\Session::class,
    \Erikwang2013\BigData\Integration\Webman\Bootstrap::class,
];
```

On the first worker start, `Bootstrap` copies its template to
`config/bigdata.php` when the file is missing; an existing file is never
overwritten. The resulting config:

```php
return [
    'default' => getenv('BIGDATA_DEFAULT') ?: 'trino',
    'drivers' => [
        'trino' => [
            'host' => getenv('BIGDATA_TRINO_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('BIGDATA_TRINO_PORT') ?: 8080),
            'user' => getenv('BIGDATA_TRINO_USER') ?: 'php',
            'catalog' => getenv('BIGDATA_TRINO_CATALOG') ?: 'hive',
            'schema' => getenv('BIGDATA_TRINO_SCHEMA') ?: 'default',
        ],
    ],
];
```

The bootstrap registers a lazy definition in the application container, once
per worker process; no driver is created during worker start-up.

## Usage

Resolve the manager from the Webman container:

```php
use Erikwang2013\BigData\Integration\Manager;
use support\Container;

$rows = Container::get(Manager::class)->driver()->query('SELECT 1 AS id')->rows();
$jobs = Container::get(Manager::class)->driver('airflow')->listDags();
```

`Container::get(Manager::class)` returns the same instance for the life of the
worker process, so it is safe to call from any action. If you prefer dependency
injection, register definitions for your controllers as well.
