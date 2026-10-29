<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Support;

/**
 * Copies a framework config template only when the application has not
 * created one yet. Failures are silent: a read-only deploy must not break
 * the application, and the adapter still falls back to the shipped defaults.
 */
final class ConfigPublisher
{
    public static function publishIfMissing(string $source, string $destination): bool
    {
        if (!is_file($source) || is_file($destination)) {
            return false;
        }

        $directory = dirname($destination);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            return false;
        }

        // Write a sibling temp file and rename it, so concurrent workers can
        // never load a half-written config.
        $temporary = $directory . '/.bigdata-' . bin2hex(random_bytes(6));
        if (!@copy($source, $temporary)) {
            @unlink($temporary);

            return false;
        }
        @chmod($temporary, 0644);

        if (!@rename($temporary, $destination)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }
}
