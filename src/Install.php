<?php
declare(strict_types=1);

namespace Erikwang2013\BigData;

use Erikwang2013\BigData\Integration\Webman\Bootstrap;

/**
 * Webman plugin installer.
 *
 * webman's skeleton runs `support\Plugin::install` from its
 * `post-package-install` script. That script looks for
 * `src/Install.php` in each installed package's PSR-4 paths and calls
 * `Install::install()` when the `WEBMAN_PLUGIN` constant is defined.
 *
 * The generated `config/plugin/erikwang2013/bigdata-php/app.php` and
 * `bootstrap.php` are loaded by webman's plugin config loader, so no manual
 * `config/bootstrap.php` edit is needed.
 */
final class Install
{
    public const WEBMAN_PLUGIN = true;

    private const PLUGIN_DIR = 'config/plugin/erikwang2013/bigdata-php';

    public static function install(bool $isInstall = true): void
    {
        $root = self::basePath();
        if ($root === null) {
            return;
        }

        $directory = $root . DIRECTORY_SEPARATOR . self::PLUGIN_DIR;
        if (!self::ensureDirectory($directory)) {
            return;
        }

        // webman only loads a plugin config directory when app.php exists
        // and its "enable" flag is truthy.
        self::writeApp($directory . DIRECTORY_SEPARATOR . 'app.php');

        $file = $directory . DIRECTORY_SEPARATOR . 'bootstrap.php';
        $bootstraps = self::readBootstraps($file);
        if (!in_array(Bootstrap::class, $bootstraps, true)) {
            $bootstraps[] = Bootstrap::class;
        }

        self::write($file, $bootstraps);
    }

    public static function update(): void
    {
        self::install(false);
    }

    public static function uninstall(): void
    {
        $root = self::basePath();
        if ($root === null) {
            return;
        }

        $directory = $root . DIRECTORY_SEPARATOR . self::PLUGIN_DIR;
        $file = $directory . DIRECTORY_SEPARATOR . 'bootstrap.php';
        $bootstraps = array_values(array_filter(
            self::readBootstraps($file),
            static fn (string $class): bool => $class !== Bootstrap::class,
        ));

        if ($bootstraps !== []) {
            self::write($file, $bootstraps);
            return;
        }

        foreach ([$file, $directory . DIRECTORY_SEPARATOR . 'app.php'] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        if (is_dir($directory)) {
            @rmdir($directory);
        }
        @rmdir(dirname($directory));
    }

    private static function basePath(): ?string
    {
        if (function_exists('base_path')) {
            try {
                return rtrim((string) base_path(), '/\\');
            } catch (\Throwable) {
                return null;
            }
        }

        if (defined('BASE_PATH')) {
            return rtrim((string) constant('BASE_PATH'), '/\\');
        }

        return null;
    }

    /** @return list<string> */
    private static function readBootstraps(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        try {
            $value = require $file;
        } catch (\Throwable) {
            return [];
        }

        return is_array($value)
            ? array_values(array_filter($value, static fn ($class): bool => is_string($class)))
            : [];
    }

    private static function ensureDirectory(string $directory): bool
    {
        return is_dir($directory) || mkdir($directory, 0777, true) || is_dir($directory);
    }

    private static function writeApp(string $file): void
    {
        $app = [];
        if (is_file($file)) {
            try {
                $value = require $file;
            } catch (\Throwable) {
                $value = [];
            }
            if (is_array($value)) {
                $app = $value;
            }
        }
        $app['enable'] = true;

        self::writeFile(
            $file,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($app, true) . ";\n",
        );
    }

    /** @param list<string> $bootstraps */
    private static function write(string $file, array $bootstraps): void
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', '', 'return ['];
        foreach ($bootstraps as $class) {
            $lines[] = '    \\' . ltrim($class, '\\') . '::class,';
        }
        $lines[] = '];';
        $lines[] = '';

        self::writeFile($file, implode("\n", $lines));
    }

    private static function writeFile(string $file, string $content): void
    {
        $temporary = $file . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $content) === false) {
            return;
        }
        if (!rename($temporary, $file)) {
            @unlink($temporary);
        }
    }
}
