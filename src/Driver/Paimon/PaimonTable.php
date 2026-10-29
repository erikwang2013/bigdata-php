<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Paimon;

use Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException;

/**
 * Read-only view of a Paimon table's metadata: the latest schema and the snapshots.
 *
 * Only the JSON files under schema/ and snapshot/ are read; data files, manifest
 * lists and manifest files (Avro) are never touched.
 */
final class PaimonTable
{
    /** @var array<string, mixed> */
    private readonly array $schema;

    /** @var list<array<string, mixed>> */
    private readonly array $snapshots;

    private function __construct(string $basePath, PaimonStorage $storage)
    {
        $this->schema = self::readSchema($basePath, $storage);
        $this->snapshots = self::readSnapshots($basePath, $storage);
    }

    public static function open(string $basePath, PaimonStorage $storage): self
    {
        return new self(rtrim($basePath, '/'), $storage);
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        return $this->schema;
    }

    /** @return list<array<string, mixed>> */
    public function snapshots(): array
    {
        return $this->snapshots;
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        $options = $this->schema['options'] ?? [];

        return is_array($options) ? $options : [];
    }

    /** @return array<string, mixed>|null */
    public function latestSnapshot(): ?array
    {
        return $this->snapshots === [] ? null : $this->snapshots[array_key_last($this->snapshots)];
    }

    /** @return array<string, mixed> */
    private static function readSchema(string $basePath, PaimonStorage $storage): array
    {
        $files = self::numberedFiles($storage->list($basePath . '/schema'), 'schema');
        if ($files === []) {
            throw new PaimonException(sprintf('No schema files found in "%s/schema".', $basePath));
        }

        return self::decode($storage->read($basePath . '/schema/' . $files[array_key_last($files)]));
    }

    /** @return list<array<string, mixed>> */
    private static function readSnapshots(string $basePath, PaimonStorage $storage): array
    {
        $snapshots = [];
        foreach (self::numberedFiles($storage->list($basePath . '/snapshot'), 'snapshot') as $name) {
            $snapshots[] = self::decode($storage->read($basePath . '/snapshot/' . $name));
        }

        usort(
            $snapshots,
            static fn (array $a, array $b): int => ($a['id'] ?? 0) <=> ($b['id'] ?? 0),
        );

        return $snapshots;
    }

    /**
     * Files named "{prefix}-{id}", keyed by id in ascending order. Every other
     * entry in the directory (LATEST, EARLIEST, ...) is ignored.
     *
     * @param list<string> $names
     * @return array<int, string>
     */
    private static function numberedFiles(array $names, string $prefix): array
    {
        $files = [];
        foreach ($names as $name) {
            if (preg_match('/^' . $prefix . '-(\d+)$/', $name, $matches) === 1) {
                $files[(int) $matches[1]] = $name;
            }
        }

        ksort($files);

        return $files;
    }

    /** @return array<string, mixed> */
    private static function decode(string $contents): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new PaimonException('Invalid JSON in table metadata: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new PaimonException('Invalid JSON in table metadata: expected an object.');
        }

        return $decoded;
    }
}
