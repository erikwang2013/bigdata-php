<?php
declare(strict_types=1);

/*
 * Partial boot: no Hyperf DI container is started. ConfigProvider::__invoke()
 * is called and its Manager definition is run against a stub container that
 * serves a config object, which is all the definition touches.
 */

use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Integration\Hyperf\ConfigProvider;
use Erikwang2013\BigData\Integration\Manager;
use Hyperf\Contract\ConfigInterface;

$projectDir = require __DIR__ . '/bootstrap.php';

smoke_section('framework classes');
smoke_check(
    interface_exists(ConfigInterface::class),
    'Hyperf\Contract\ConfigInterface is missing; composer require hyperf/contract in SMOKE_PROJECT_DIR.',
);

// Hyperf defines BASE_PATH in its bootstrap; the publish entry needs it.
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $projectDir);
}

smoke_section('config provider');
$provider = new ConfigProvider();
$provided = $provider();

smoke_check(isset($provided['dependencies'][Manager::class]), 'dependencies should define ' . Manager::class . '.');
smoke_check(is_callable($provided['dependencies'][Manager::class]), 'the Manager definition should be callable.');

smoke_section('config publish');
smoke_check(
    is_file(BASE_PATH . '/config/autoload/bigdata.php'),
    'ConfigProvider should copy config/autoload/bigdata.php on first boot.',
);

$publish = $provided['publish'][0] ?? [];
smoke_check(($publish['id'] ?? null) === 'bigdata', 'publish entry id should be bigdata.');
smoke_check(is_file($publish['source'] ?? ''), 'publish source should point at the shipped config file.');
smoke_check(
    ($publish['destination'] ?? null) === BASE_PATH . '/config/autoload/bigdata.php',
    'publish destination should live under BASE_PATH.',
);

smoke_section('manager definition');
$config = new class {
    public function get(string $key, $default = null)
    {
        return $key === 'bigdata'
            ? ['default' => 'trino', 'drivers' => ['trino' => ['host' => '127.0.0.1']]]
            : $default;
    }
};
$container = new class($config) {
    public function __construct(private object $config)
    {
    }

    public function get(string $id): object
    {
        return $this->config;
    }
};

$manager = $provided['dependencies'][Manager::class]($container);
smoke_check(
    $manager instanceof Manager,
    'the Manager definition should return ' . Manager::class . ', got ' . get_debug_type($manager),
);
smoke_check($manager->drivers() === ['trino'], 'configured drivers should be [trino].');

$client = $manager->driver();
smoke_check($client instanceof TrinoClient, 'driver() should return TrinoClient, got ' . get_debug_type($client));

smoke_pass('hyperf', 'partial');
