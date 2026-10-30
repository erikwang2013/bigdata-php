<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Yii2;

use yii\base\Application;
use yii\base\BootstrapInterface;

/**
 * Yii2 bootstrap class declared under `extra.bootstrap`.
 *
 * `yiisoft/yii2-composer` records this class in vendor/yiisoft/extensions.php;
 * Yii2 then calls bootstrap() during application start-up. The component is
 * registered lazily, so no driver is created until the application uses it.
 */
final class BigDataBootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        if (!$app instanceof Application || $app->has('bigdata')) {
            return;
        }

        $app->set('bigdata', ['class' => BigDataComponent::class]);
    }
}
