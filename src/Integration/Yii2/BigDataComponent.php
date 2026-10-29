<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Yii2;

use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Support\ConfigPublisher;
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
 * Driver config is read from Yii::$app->params['bigdata']. When that is not
 * set, config/bigdata.php is created from the package template on first use.
 */
class BigDataComponent extends Component
{
    private ?Manager $manager = null;

    public function getManager(): Manager
    {
        if ($this->manager === null) {
            $this->manager = new Manager($this->config());
        }

        return $this->manager;
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        $config = Yii::$app->params['bigdata'] ?? null;
        if (is_array($config) && $config !== []) {
            return $config;
        }

        $template = __DIR__ . '/config/bigdata.php';
        $destination = Yii::$app->getBasePath() . '/config/bigdata.php';
        ConfigPublisher::publishIfMissing($template, $destination);

        return is_file($destination) ? require $destination : require $template;
    }

    public function driver(?string $name = null): object
    {
        return $this->getManager()->driver($name);
    }
}
