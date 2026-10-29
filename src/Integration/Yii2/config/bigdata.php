<?php
declare(strict_types=1);

/*
 * Yii2 has no config auto-loading. Return this array under
 * params['bigdata'] in config/web.php (and config/console.php):
 *
 *     'params' => ['bigdata' => require __DIR__ . '/bigdata.php'],
 */
return [
    'default' => 'trino',
    'drivers' => [
        'trino' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'user' => 'php',
            'catalog' => 'hive',
            'schema' => 'default',
        ],

        // 'airflow' => [
        //     'baseUrl' => 'http://127.0.0.1:8080',
        //     'username' => 'admin',
        //     'password' => 'admin',
        // ],
    ],
];
