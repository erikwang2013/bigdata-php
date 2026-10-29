# Yii2

| | |
|---|---|
| Adapter | `Erikwang2013\BigData\Integration\Yii2\BigDataComponent` |
| Framework floor | Yii2 2.0.13+ (2.0.46+ recommended on PHP 8) |
| Adapter PHP floor | 8.0 |
| Component ID | `bigdata` |

## Registration

Yii2 has no Composer auto-discovery. Register the component and its params in
`config/web.php` (and `config/console.php` when the console needs it):

```php
'components' => [
    'bigdata' => [
        'class' => \Erikwang2013\BigData\Integration\Yii2\BigDataComponent::class,
    ],
],
'params' => [
    'bigdata' => require __DIR__ . '/bigdata.php',
],
```

Copy `src/Integration/Yii2/config/bigdata.php` to `config/bigdata.php`. It
returns the manager config directly:

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
