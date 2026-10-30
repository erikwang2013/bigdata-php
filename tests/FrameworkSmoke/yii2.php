<?php
declare(strict_types=1);

/*
 * Full boot: a minimal Yii2 console application with the bigdata component
 * configured through params["bigdata"]. No driver method is called.
 */

use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Integration\Yii2\BigDataBootstrap;
use Erikwang2013\BigData\Integration\Yii2\BigDataComponent;
use yii\base\BootstrapInterface;
use yii\console\Application;

$projectDir = require __DIR__ . '/bootstrap.php';

smoke_section('framework classes');
// Yii2 entry scripts require Yii.php explicitly; Composer does not autoload it.
$yiiFile = $projectDir . '/vendor/yiisoft/yii2/Yii.php';
smoke_check(is_file($yiiFile), "{$yiiFile} is missing; composer require yiisoft/yii2 in SMOKE_PROJECT_DIR.");
require $yiiFile;

smoke_check(class_exists(Application::class), 'yii\console\Application is missing; composer require yiisoft/yii2 in SMOKE_PROJECT_DIR.');
smoke_check(class_exists(\Yii::class), 'Yii was not defined by vendor/yiisoft/yii2/Yii.php.');
smoke_check(class_exists(BigDataBootstrap::class), BigDataBootstrap::class . ' is missing.');
smoke_check(
    is_subclass_of(BigDataBootstrap::class, BootstrapInterface::class),
    BigDataBootstrap::class . ' should implement yii\base\BootstrapInterface.',
);

smoke_section('console application');
$config = require SMOKE_PACKAGE_ROOT . '/src/Integration/Yii2/config/bigdata.php';
smoke_check(($config['default'] ?? null) === 'trino', 'Yii2 config should default to trino.');

$app = new Application([
    'id' => 'bigdata-smoke',
    'basePath' => $projectDir,
    'vendorPath' => $projectDir . '/vendor',
    'components' => [
        'bigdata' => BigDataComponent::class,
    ],
]);

smoke_section('component binding and config publish');
$component = $app->bigdata;
smoke_check(
    $component instanceof BigDataComponent,
    "'bigdata' should resolve to " . BigDataComponent::class . ', got ' . get_debug_type($component),
);
smoke_check($component->getManager() instanceof Manager, 'the component manager should be ' . Manager::class . '.');
smoke_check(
    is_file($projectDir . '/config/bigdata.php'),
    'the component should copy config/bigdata.php when params["bigdata"] is not set.',
);

smoke_section('bootstrap auto-registration');
$autoApp = new Application([
    'id' => 'bigdata-smoke-auto',
    'basePath' => $projectDir,
    'vendorPath' => $projectDir . '/vendor',
]);
(new BigDataBootstrap())->bootstrap($autoApp);
smoke_check($autoApp->has('bigdata'), 'BigDataBootstrap should register the bigdata component.');
smoke_check(
    $autoApp->get('bigdata') instanceof BigDataComponent,
    'bigdata should resolve to ' . BigDataComponent::class . ' after bootstrap.',
);

$client = $component->driver('trino');
smoke_check($client instanceof TrinoClient, 'driver("trino") should return TrinoClient, got ' . get_debug_type($client));

smoke_pass('yii2', 'full');
