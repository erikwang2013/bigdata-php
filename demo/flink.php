<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\BigData\BigData;

try {
    $gateway = BigData::driver('flink-sql', ['baseUrl' => 'http://127.0.0.1:8083']);

    // Batch mode makes SELECT 1 a finite job the gateway can finish and page out.
    $session = $gateway->openSession(['execution.runtime-mode' => 'batch']);

    try {
        $operation = $gateway->executeStatement($session, 'SELECT 1');

        $token = 0;
        while ($token !== null) {
            $page = $gateway->fetchResults($session, $operation, $token);
            foreach ($page['rows'] as $row) {
                echo implode("\t", $row), PHP_EOL;
            }
            $token = $page['nextToken'];
        }
    } finally {
        $gateway->closeSession($session);
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
