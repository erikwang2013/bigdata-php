<?php
declare(strict_types=1);

/*
 * Partial boot: class-load only. Booting think\App needs a configured project
 * (config path, providers, runtime directory), so this checks the service
 * contract and the shipped config shape instead.
 */

use Erikwang2013\BigData\Integration\ThinkPHP\BigDataService;
use think\Service;

require __DIR__ . '/bootstrap.php';

smoke_section('framework classes');
smoke_check(class_exists(Service::class), 'think\Service is missing; composer require topthink/framework in SMOKE_PROJECT_DIR.');

smoke_section('service contract');
smoke_check(class_exists(BigDataService::class), BigDataService::class . ' does not autoload.');
smoke_check(is_subclass_of(BigDataService::class, Service::class), BigDataService::class . ' should extend think\Service.');
smoke_check(method_exists(BigDataService::class, 'register'), BigDataService::class . ' should declare register().');

smoke_section('config shape');
$config = require SMOKE_PACKAGE_ROOT . '/src/Integration/ThinkPHP/config/bigdata.php';
$expectedDefault = getenv('BIGDATA_DEFAULT') ?: 'trino';

smoke_check(is_array($config), 'ThinkPHP config file should return an array.');
smoke_check(($config['default'] ?? null) === $expectedDefault, 'config default should honour BIGDATA_DEFAULT and fall back to trino.');
smoke_check(isset($config['drivers']['trino']), 'config should define the trino driver.');

foreach (['host', 'port', 'user', 'catalog', 'schema'] as $key) {
    smoke_check(array_key_exists($key, $config['drivers']['trino']), "config drivers.trino should define {$key}.");
}
smoke_check(is_int($config['drivers']['trino']['port']), 'config drivers.trino.port should be an int.');

smoke_pass('thinkphp', 'partial');
