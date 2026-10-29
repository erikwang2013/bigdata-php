<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Webman;

use Erikwang2013\BigData\Integration\Manager;
use support\Container;
use Webman\Bootstrap as WebmanBootstrap;

class Bootstrap implements WebmanBootstrap
{
    /**
     * Adds a lazy definition instead of instantiating eagerly, so worker
     * start-up does no driver work and every process gets its own Manager.
     */
    public static function start($worker): void
    {
        Container::instance()->addDefinitions([
            Manager::class => static function (): Manager {
                return new Manager(config('bigdata', []));
            },
        ]);
    }
}
