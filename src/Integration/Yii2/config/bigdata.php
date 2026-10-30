<?php
declare(strict_types=1);

/*
 * Yii2 auto-registers the bigdata component through extra.bootstrap. This
 * file is created on first use and is read when params['bigdata'] is not set.
 * The array can also be returned under params['bigdata'] in config/web.php
 * (and config/console.php), which takes precedence:
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
