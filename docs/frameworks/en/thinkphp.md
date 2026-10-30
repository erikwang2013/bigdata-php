# ThinkPHP

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\ThinkPHP\BigDataService` |
| Framework floor | ThinkPHP 8 (PHP 8.0+) |
| Adapter PHP floor | 8.0 |
| Container binding | `Manager::class` and `bigdata` |

## Registration

The package declares the service under `extra.think.services`. The
`topthink/think` skeleton runs discovery automatically after `composer
require` through its `post-autoload-dump` script:

```bash
php think service:discover
```

Run that command by hand only when the project has no such script.

Without auto-discovery, list the service in `app/service.php`:

```php
return [
    \app\AppService::class,
    \Erikwang2013\BigData\Integration\ThinkPHP\BigDataService::class,
];
```

## Configuration

On the first service boot, `BigDataService` copies its template to
`config/bigdata.php` when the application has no `bigdata` config. Existing
files are never overwritten. The resulting file:

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
