<?php
declare(strict_types=1);

/*
 * Partial boot: class-load only. Bootstrap::start() needs a running webman
 * worker plus support\Container, so this checks the bootstrap contract and
 * the shipped config shape instead.
 */

use Erikwang2013\BigData\Install;
use Erikwang2013\BigData\Integration\Webman\Bootstrap;
use Webman\Bootstrap as WebmanBootstrap;

require __DIR__ . '/bootstrap.php';

smoke_section('framework classes');
smoke_check(
    interface_exists(WebmanBootstrap::class),
    'Webman\Bootstrap is missing; composer require workerman/webman-framework in SMOKE_PROJECT_DIR.',
);

smoke_section('bootstrap contract');
smoke_check(class_exists(Bootstrap::class), Bootstrap::class . ' does not autoload.');
smoke_check(is_subclass_of(Bootstrap::class, WebmanBootstrap::class), Bootstrap::class . ' should implement Webman\Bootstrap.');
smoke_check(method_exists(Bootstrap::class, 'start'), Bootstrap::class . ' should define start().');

smoke_section('plugin installer');
smoke_check(class_exists(Install::class), Install::class . ' does not autoload.');
smoke_check(Install::WEBMAN_PLUGIN === true, 'Install::WEBMAN_PLUGIN should be true.');
smoke_check(method_exists(Install::class, 'install'), Install::class . ' should define install().');

smoke_section('config shape');
$config = require SMOKE_PACKAGE_ROOT . '/src/Integration/Webman/config/bigdata.php';
$expectedDefault = getenv('BIGDATA_DEFAULT') ?: 'trino';

smoke_check(is_array($config), 'Webman config file should return an array.');
smoke_check(($config['default'] ?? null) === $expectedDefault, 'config default should honour BIGDATA_DEFAULT and fall back to trino.');
smoke_check(isset($config['drivers']['trino']), 'config should define the trino driver.');

foreach (['host', 'port', 'user', 'catalog', 'schema'] as $key) {
    smoke_check(array_key_exists($key, $config['drivers']['trino']), "config drivers.trino should define {$key}.");
}
smoke_check(is_int($config['drivers']['trino']['port']), 'config drivers.trino.port should be an int.');

smoke_pass('webman', 'partial');
