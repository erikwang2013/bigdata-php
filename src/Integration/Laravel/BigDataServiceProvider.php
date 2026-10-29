<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Laravel;

use Erikwang2013\BigData\Integration\Manager;
use Illuminate\Support\ServiceProvider;

class BigDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/bigdata.php', 'bigdata',
        );

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
