# ThinkPHP

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\ThinkPHP\BigDataService` |
| Framework floor | ThinkPHP 8 (PHP 8.0+) |
| Adapter PHP floor | 8.0 |
| Container binding | `Manager::class` and `bigdata` |

## Registration

The package declares the service under `extra.think.services`. After
installing, refresh the service map:

```bash
php think service:discover
```

Without auto-discovery, list the service in `app/service.php`:

```php
return [
    \app\AppService::class,
    \Erikwang2013\BigData\Integration\ThinkPHP\BigDataService::class,
];
```

## Configuration

Copy `src/Integration/ThinkPHP/config/bigdata.php` to `config/bigdata.php`:

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

Add more drivers under `drivers`; keys are the names accepted by
`BigData::drivers()`. Commented `airflow` example in the template file.

## Usage

```php
use Erikwang2013\BigData\Integration\Manager;

final class ReportController
{
    public function __construct(private Manager $bigdata)
    {
    }

    public function index()
    {
        $rows = $this->bigdata->driver()->query('SELECT 1 AS id')->rows();

        return json($rows);
    }
}
```

`app(Manager::class)` and `app('bigdata')` resolve the same instance.
