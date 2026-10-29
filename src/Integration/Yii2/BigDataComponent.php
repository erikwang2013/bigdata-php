<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Yii2;

use Erikwang2013\BigData\Integration\Manager;
use Yii;
use yii\base\Component;

/**
 * Yii2 application component:
 *
 *     'components' => [
 *         'bigdata' => \Erikwang2013\BigData\Integration\Yii2\BigDataComponent::class,
 *     ],
 *
 *     Yii::$app->bigdata->driver()->query('SELECT 1');
 *
 * Driver config is read from Yii::$app->params['bigdata'] (see config/bigdata.php).
 */
class BigDataComponent extends Component
{
    private ?Manager $manager = null;

    public function getManager(): Manager
    {
        if ($this->manager === null) {
            $this->manager = new Manager(Yii::$app->params['bigdata'] ?? []);
        }

        return $this->manager;
    }

    public function driver(?string $name = null): object
    {
        return $this->getManager()->driver($name);
    }
}
