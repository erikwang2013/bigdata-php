# Laravel

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\Laravel\BigDataServiceProvider` |
| Framework floor | Laravel 9+ (Laravel 10 needs PHP 8.1, Laravel 11+ needs PHP 8.2) |
| Adapter PHP floor | 8.0 |
| Container binding | `Manager::class` and `bigdata` |

## Registration

The package declares the provider in Composer, so Laravel discovers it
automatically. No `config/app.php` edit is needed.

If you disabled package discovery, register it manually.

Laravel 9/10 (`config/app.php`):

```php
'providers' => [
    Erikwang2013\BigData\Integration\Laravel\BigDataServiceProvider::class,
],
```

Laravel 11+ (`bootstrap/providers.php`):

```php
return [
    Erikwang2013\BigData\Integration\Laravel\BigDataServiceProvider::class,
];
```

## Configuration

The provider copies the template to `config/bigdata.php` on first boot when the
file does not exist; an existing file is never overwritten. The same file can
be published explicitly:

```bash
php artisan vendor:publish --tag=bigdata-config
```

Published config:

```php
return [
    'default' => env('BIGDATA_DEFAULT', 'trino'),
    'drivers' => [
        'trino' => [
            'host' => env('BIGDATA_TRINO_HOST', '127.0.0.1'),
            'port' => (int) env('BIGDATA_TRINO_PORT', 8080),
            'user' => env('BIGDATA_TRINO_USER', 'php'),
            'catalog' => env('BIGDATA_TRINO_CATALOG', 'hive'),
            'schema' => env('BIGDATA_TRINO_SCHEMA', 'default'),
        ],
    ],
];
```

Add more entries under `drivers`; keys are the names accepted by
`BigData::drivers()` (`airflow`, `spark`, `doris`, ...). The template
`src/Integration/Laravel/config/bigdata.php` contains a commented example.

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
        $jobs = $this->bigdata->driver('airflow')->listDags();

        return response()->json($rows);
    }
}
```

`app(Manager::class)` and `app('bigdata')` resolve the same singleton.
