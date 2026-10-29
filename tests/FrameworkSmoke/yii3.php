<?php
declare(strict_types=1);

/*
 * Partial boot: no yiisoft/config merge step or DI container is built. The
 * shipped params/di files and ConfigProvider are validated directly.
 */

use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Integration\Yii3\ConfigProvider;
use Yiisoft\Config\Config;

require __DIR__ . '/bootstrap.php';

smoke_section('framework classes');
smoke_check(class_exists(Config::class), 'Yiisoft\Config\Config is missing; composer require yiisoft/config in SMOKE_PROJECT_DIR.');

smoke_section('shipped params');
$params = require SMOKE_PACKAGE_ROOT . '/config/params.php';
smoke_check(
    isset($params['erikwang2013/bigdata-php']['drivers']['trino']),
    'config/params.php should define the trino driver.',
);
smoke_check(
    ($params['erikwang2013/bigdata-php']['default'] ?? null) === 'trino',
    'config/params.php should default to trino.',
);

smoke_section('config provider');
$provider = new ConfigProvider();
$provided = $provider();

smoke_check(($provided['params'] ?? []) === $params, 'provider params should match config/params.php.');
smoke_check(isset($provided['di'][Manager::class]), 'config/di.php should define ' . Manager::class . '.');
smoke_check(is_callable($provided['di'][Manager::class]), 'the Manager DI definition should be callable.');

$manager = $provided['di'][Manager::class]();
smoke_check(
    $manager instanceof Manager,
    'the Manager DI definition should return ' . Manager::class . ', got ' . get_debug_type($manager),
);
smoke_check($manager->drivers() === ['trino'], 'configured drivers should be [trino].');

$client = $manager->driver();
smoke_check($client instanceof TrinoClient, 'driver() should return TrinoClient, got ' . get_debug_type($client));

smoke_pass('yii3', 'partial');
