<?php
declare(strict_types=1);

/*
 * Full boot on Laravel 10 components: register the service provider on a
 * plain Illuminate\Container\Container with a config repository bound, then
 * resolve the "bigdata" alias. boot() is intentionally not called because
 * its config_path() call expects a full Foundation Application.
 */

use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Integration\Laravel\BigDataServiceProvider;
use Erikwang2013\BigData\Integration\Manager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

require __DIR__ . '/bootstrap.php';

// The provider publishes into config_path(); the plain container smoke has no
// Foundation application, so provide the helper Laravel would define.
if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        $directory = getenv('SMOKE_PROJECT_DIR') . '/config';

        return $path === '' ? $directory : $directory . '/' . $path;
    }
}

smoke_section('framework classes');
$required = [
    Container::class => 'illuminate/container',
    Repository::class => 'illuminate/config',
    ServiceProvider::class => 'illuminate/support',
];

foreach ($required as $class => $package) {
    smoke_check(class_exists($class), "{$class} is missing; composer require {$package} in SMOKE_PROJECT_DIR.");
}

smoke_section('provider register');
$container = new Container();
$container->instance('config', new Repository([]));
// Application values are shallow-merged over the package defaults.
$container->make('config')->set('bigdata', [
    'drivers' => ['trino' => ['host' => 'trino.internal']],
]);

(new BigDataServiceProvider($container))->register();

smoke_section('config publish');
$publishedConfig = config_path('bigdata.php');
smoke_check(is_file($publishedConfig), 'the provider should copy config/bigdata.php on first boot.');
smoke_check(
    (require $publishedConfig)['default'] === 'trino',
    'the published config should keep the package default driver.',
);

smoke_section('container bindings');
$config = $container->make('config');
smoke_check(($config->get('bigdata.default') ?? null) === 'trino', 'package config should provide default=trino.');
smoke_check(
    ($config->get('bigdata.drivers.trino.host') ?? null) === 'trino.internal',
    'application config should override drivers.trino.host.',
);

$manager = $container->make('bigdata');
smoke_check(
    $manager instanceof Manager,
    "'bigdata' should resolve to " . Manager::class . ', got ' . get_debug_type($manager),
);
smoke_check($container->make(Manager::class) === $manager, 'Manager should be bound as a singleton.');
smoke_check($manager->drivers() === ['trino'], 'configured drivers should be [trino].');

$client = $manager->driver('trino');
smoke_check($client instanceof TrinoClient, 'driver("trino") should return TrinoClient, got ' . get_debug_type($client));

smoke_pass('laravel', 'full');
