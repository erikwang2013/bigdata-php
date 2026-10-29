# Hyperf

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\Hyperf\ConfigProvider` |
| Framework floor | Hyperf 3.x (PHP 8.0+) |
| Adapter PHP floor | 8.0 |
| Container binding | `Manager::class` |

## Registration

Hyperf discovers the provider through `extra.hyperf.config`, so no manual
registration is needed after `composer require`.

If auto-discovery is disabled, add the definition to
`config/autoload/dependencies.php` and copy the config template manually:

```php
// config/autoload/dependencies.php
use Erikwang2013\BigData\Integration\Manager;
use Hyperf\Contract\ConfigInterface;

return [
    Manager::class => static function ($container): Manager {
        return new Manager($container->get(ConfigInterface::class)->get('bigdata', []));
    },
];
```

`ConfigProvider` copies the template to `config/autoload/bigdata.php` on first
boot when the file is missing; existing files are never overwritten. The same
file can be published explicitly:

```bash
php bin/hyperf.php vendor:publish erikwang2013/bigdata-php
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

The config template also ships a commented `airflow` example under
`src/Integration/Hyperf/config/bigdata.php`.

## Usage

```php
use Erikwang2013\BigData\Integration\Manager;

final class ReportService
{
    public function __construct(private Manager $bigdata)
    {
    }

    public function rows(): array
    {
        return $this->bigdata->driver()->query('SELECT 1 AS id')->rows();
    }
}
```

`$container->get(Manager::class)` and annotation injection both resolve the
same instance.
