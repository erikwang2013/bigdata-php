<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\BigData\BigData;

// The compose file publishes Trino on 8080 by default; set TRINO_PORT if 8080 is taken.
$port = (int) (getenv('TRINO_PORT') ?: 8080);

try {
    $trino = BigData::driver('trino', [
        'host' => '127.0.0.1',
        'port' => $port,
        'user' => 'php',
    ]);

    $result = $trino->query('SELECT 1');
    foreach ($result->rows() as $row) {
        echo $row[0], PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
