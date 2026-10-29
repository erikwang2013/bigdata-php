<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Webman;

use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Support\ConfigPublisher;
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
        $template = __DIR__ . '/config/bigdata.php';

        if (function_exists('base_path')) {
            ConfigPublisher::publishIfMissing(
                $template,
                base_path('config/bigdata.php'),
            );
        }

        Container::instance()->addDefinitions([
            Manager::class => static function () use ($template): Manager {
                $config = config('bigdata');
                if (!is_array($config) || $config === []) {
                    $config = require $template;
                }

                return new Manager($config);
            },
        ]);
    }
}
