<?php
declare(strict_types=1);

/*
 * Shared bootstrap for the framework smoke scripts.
 *
 * Two autoloaders are needed: this package (installed in the repository) and
 * the throw-away project that holds the framework packages. The workflow sets
 * SMOKE_PROJECT_DIR; scripts can be run by hand with the same variable.
 */

define('SMOKE_PACKAGE_ROOT', dirname(__DIR__, 2));

$packageAutoload = SMOKE_PACKAGE_ROOT . '/vendor/autoload.php';
if (!is_file($packageAutoload)) {
    fwrite(STDERR, "FAIL: {$packageAutoload} is missing; run composer install first.\n");
    exit(1);
}
require $packageAutoload;

$projectDir = getenv('SMOKE_PROJECT_DIR');
if ($projectDir === false || $projectDir === '') {
    fwrite(STDERR, "FAIL: SMOKE_PROJECT_DIR is not set; point it at the project holding the framework packages.\n");
    exit(1);
}

$projectAutoload = rtrim($projectDir, '/\\') . '/vendor/autoload.php';
if (!is_file($projectAutoload)) {
    fwrite(STDERR, "FAIL: {$projectAutoload} is missing; composer require the framework packages in SMOKE_PROJECT_DIR first.\n");
    exit(1);
}
require $projectAutoload;

function smoke_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function smoke_section(string $label): void
{
    echo "-- {$label}\n";
}

function smoke_pass(string $framework, string $mode): void
{
    echo "PASS [{$mode}]: {$framework}\n";
}

return $projectDir;
