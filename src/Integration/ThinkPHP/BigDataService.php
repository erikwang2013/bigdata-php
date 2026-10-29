<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\ThinkPHP;

use Erikwang2013\BigData\Integration\Manager;
use think\Service;

class BigDataService extends Service
{
    public function register(): void
    {
        $manager = new Manager($this->app->config->get('bigdata', []));

        $this->app->instance('bigdata', $manager);
        $this->app->instance(Manager::class, $manager);
    }
}
