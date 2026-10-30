# Yii2

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\Yii2\BigDataComponent` |
| Framework floor | Yii2 2.0.13+ (2.0.46+ recommended on PHP 8) |
| Adapter PHP floor | 8.0 |
| Component ID | `bigdata` |

## Registration

The package is declared as `type=yii2-extension` and registers
`BigDataBootstrap` under `extra.bootstrap`. `yiisoft/yii2-composer` adds the
component automatically, so no `config/web.php` or `config/console.php` edit is
needed. Non-Yii2 projects install it as a normal library.

If Yii2's Composer plugin is disabled, register the component manually:

```php
'components' => [
    'bigdata' => [
        'class' => \Erikwang2013\BigData\Integration\Yii2\BigDataComponent::class,
    ],
],
```

The component copies `src/Integration/Yii2/config/bigdata.php` to
`config/bigdata.php` on first use when `params['bigdata']` is not set and the
file is missing; existing files are never overwritten. When you set `params['bigdata']`, it takes
precedence over the file. The copied file returns the manager config directly:

```php
return [
    'default' => 'trino',
    'drivers' => [
        'trino' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'user' => 'php',
            'catalog' => 'hive',
            'schema' => 'default',
        ],
    ],
];
```

Driver config may also be set programmatically before first use:

```php
Yii::$app->params['bigdata'] = ['default' => 'trino', 'drivers' => [/* ... */]];
```

## Usage

```php
$rows = Yii::$app->bigdata->driver()->query('SELECT 1 AS id')->rows();
$jobs = Yii::$app->bigdata->driver('airflow')->listDags();

// The underlying manager is available for multiple drivers:
$manager = Yii::$app->bigdata->getManager();
```

The component is lazy: the `Manager` is created on first `driver()` call, and
each driver instance is cached for the life of the request.
