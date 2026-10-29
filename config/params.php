<?php
declare(strict_types=1);

/*
 * Yii3 (yiisoft/config) reads this file via the package's extra.config-plugin.
 * Override the "erikwang2013/bigdata-php" section in the application's own
 * config/params.php; the application values are deep-merged over these defaults.
 */
return [
    'erikwang2013/bigdata-php' => [
        'default' => 'trino',
        'drivers' => [
            'trino' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'user' => 'php',
                'catalog' => 'hive',
                'schema' => 'default',
            ],
        ],
    ],
];
