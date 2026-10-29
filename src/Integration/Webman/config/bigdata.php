<?php
declare(strict_types=1);

/*
 * Copy to config/bigdata.php. Environment variables fall back to defaults
 * when unset or empty.
 */
return [
    'default' => getenv('BIGDATA_DEFAULT') ?: 'trino',
    'drivers' => [
        'trino' => [
            'host' => getenv('BIGDATA_TRINO_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('BIGDATA_TRINO_PORT') ?: 8080),
            'user' => getenv('BIGDATA_TRINO_USER') ?: 'php',
            'catalog' => getenv('BIGDATA_TRINO_CATALOG') ?: 'hive',
            'schema' => getenv('BIGDATA_TRINO_SCHEMA') ?: 'default',
        ],

        // 'airflow' => [
        //     'baseUrl' => getenv('BIGDATA_AIRFLOW_URL') ?: 'http://127.0.0.1:8080',
        //     'username' => getenv('BIGDATA_AIRFLOW_USERNAME') ?: 'admin',
        //     'password' => getenv('BIGDATA_AIRFLOW_PASSWORD') ?: 'admin',
        // ],
    ],
];
