# Yii3

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\Yii3\ConfigProvider` |
| Framework floor | yiisoft/config 1.x, yiisoft/di 3.x (PHP 8.0+) |
| Adapter PHP floor | 8.0 |
| Container binding | `Manager::class` |

## Registration

The package declares `extra.config-plugin` with `params` and `di`, so
yiisoft/config picks up `config/params.php` and `config/di.php` automatically
after `composer require`.

Override the defaults in the application's `config/params.php`:

```php
return [
    'erikwang2013/bigdata-php' => [
        'default' => 'trino',
        'drivers' => [
            'trino' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'user' => 'php',
                'catalog' => 'hive',
                'schema' => 'default',
            ],
            // 'airflow' => [
            //     'baseUrl' => 'http://127.0.0.1:8080',
            //     'username' => 'admin',
            //     'password' => 'admin',
            // ],
        ],
    ],
];
```

Application params are deep-merged over the package defaults.

If the Composer plugin is disabled, use the provider class in your own
configuration:

```php
$provider = new \Erikwang2013\BigData\Integration\Yii3\ConfigProvider();
$params = array_replace_recursive($params, $provider->getParams());
$definitions = array_replace($definitions, $provider->getDi());
```

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

## Version floors

- `yiisoft/config` ^1.0
- `yiisoft/di` ^3.0
- PHP >= 8.0 for the adapter
