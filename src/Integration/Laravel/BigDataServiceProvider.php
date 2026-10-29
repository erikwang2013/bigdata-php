<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Laravel;

use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Support\ConfigPublisher;
use Illuminate\Support\ServiceProvider;

class BigDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/bigdata.php', 'bigdata',
        );

        // Laravel's publish convention writes to config/. Copy on first boot
        // too, so the application can edit the file without running artisan.
        if (function_exists('config_path')) {
            ConfigPublisher::publishIfMissing(
                __DIR__ . '/config/bigdata.php',
                config_path('bigdata.php'),
            );
        }

        $this->app->singleton(Manager::class, function ($app) {
            return new Manager($app['config']['bigdata']);
        });

        $this->app->alias(Manager::class, 'bigdata');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/bigdata.php' => config_path('bigdata.php'),
        ], 'bigdata-config');
    }
}
