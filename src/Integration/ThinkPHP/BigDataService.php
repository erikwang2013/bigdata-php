<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\ThinkPHP;

use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Support\ConfigPublisher;
use think\Service;

class BigDataService extends Service
{
    public function register(): void
    {
        $template = __DIR__ . '/config/bigdata.php';
        $config = $this->app->config->get('bigdata');

        if (!is_array($config) || $config === []) {
            ConfigPublisher::publishIfMissing($template, $this->configPath());
            $config = require $template;
        }

        $manager = new Manager($config);

        $this->app->instance('bigdata', $manager);
        $this->app->instance(Manager::class, $manager);
    }

    private function configPath(): string
    {
        $directory = method_exists($this->app, 'getConfigPath')
            ? $this->app->getConfigPath()
            : $this->app->getRootPath() . 'config' . DIRECTORY_SEPARATOR;

        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'bigdata.php';
    }
}
