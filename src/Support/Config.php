<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Support;

final class Config
{
    /** @param list<string> $keys */
    public static function string(array $config, array $keys, ?string $default = null): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '') {
                return (string) $config[$key];
            }
        }

        return $default;
    }

    /** @param list<string> $keys */
    public static function bool(array $config, array $keys, bool $default = true): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $config)) {
                continue;
            }

            $value = $config[$key];
            if (is_bool($value)) {
                return $value;
            }
            if (is_string($value)) {
                return !in_array(strtolower(trim($value)), ['0', 'false', 'no', 'off'], true);
            }

            return (bool) $value;
        }

        return $default;
    }

    /** @param list<string> $keys */
    public static function int(array $config, array $keys, int $default = 0): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '') {
                return (int) $config[$key];
            }
        }

        return $default;
    }

    /** @param list<string> $keys */
    public static function float(array $config, array $keys, float $default = 0.0): float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '') {
                return (float) $config[$key];
            }
        }

        return $default;
    }
}
