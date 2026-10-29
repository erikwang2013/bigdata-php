<?php
declare(strict_types=1);

/*
 * Common configuration template. Copy it to your framework's config location
 * (or use the matching src/Integration/<Framework>/config/bigdata.php).
 *
 * Keys under "drivers" are the names accepted by BigData::driver() and
 * BigData::drivers(). "default" selects the driver used when none is named.
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

        // 'spark' => [
        //     'host' => '127.0.0.1',
        //     'port' => 8998,
        // ],
    ],
];
