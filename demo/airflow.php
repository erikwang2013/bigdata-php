<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\BigData\BigData;

try {
    $airflow = BigData::driver('airflow', [
        'baseUrl' => 'http://127.0.0.1:8081',
        'username' => 'admin',
        'password' => 'admin',
    ]);

    $dags = $airflow->dags();
    $total = $dags['total_entries'] ?? count($dags['dags'] ?? []);
    echo 'DAGs: ', $total, PHP_EOL;

    foreach ($dags['dags'] ?? [] as $dag) {
        echo '- ', $dag['dag_id'], PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
