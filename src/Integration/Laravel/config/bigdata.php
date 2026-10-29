<?php
declare(strict_types=1);

/*
 * Published to config/bigdata.php. Driver names match BigData::drivers();
 * add or remove entries under "drivers" as needed.
 */
return [
    'default' => env('BIGDATA_DEFAULT', 'trino'),
    'drivers' => [
        'trino' => [
            'host' => env('BIGDATA_TRINO_HOST', '127.0.0.1'),
            'port' => (int) env('BIGDATA_TRINO_PORT', 8080),
            'user' => env('BIGDATA_TRINO_USER', 'php'),
            'catalog' => env('BIGDATA_TRINO_CATALOG', 'hive'),
            'schema' => env('BIGDATA_TRINO_SCHEMA', 'default'),
        ],

        // 'airflow' => [
        //     'baseUrl' => env('BIGDATA_AIRFLOW_URL', 'http://127.0.0.1:8080'),
        //     'username' => env('BIGDATA_AIRFLOW_USERNAME', 'admin'),
        //     'password' => env('BIGDATA_AIRFLOW_PASSWORD', 'admin'),
        // ],
    ],
];
