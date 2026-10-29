<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DeltaLake;

use Erikwang2013\BigData\Driver\DeltaLake\Exception\DeltaLakeException;

/**
 * Read-only view of a Delta table's transaction log (_delta_log).
 *
 * Every "NNNNNNNNNNNNNNNNNNNN.json" commit file is replayed in version order.
 * protocol and metaData are the last seen values, add/remove actions are replayed
 * to the current set of active files, and commitInfo is kept per version.
 *
 * Only newline-delimited JSON commits are read. Checkpoints
 * ("*.checkpoint.parquet") and "_last_checkpoint" are ignored, so a table whose
 * old commits were cleaned up may expose incomplete history.
 */
final class DeltaLog
{
    /** @var list<int> */
    private array $versions = [];

    /** @var array<string, mixed> */
    private array $protocol = [];

    /** @var array<string, mixed> */
    private array $metadata = [];

    /** @var array<string, array<string, mixed>> */
    private array $files = [];

    /** @var array<int, array<string, mixed>> */
    private array $commitInfos = [];

    private function __construct(string $basePath, DeltaStorage $storage)
    {
        $commits = [];
        foreach ($storage->list($basePath . '/_delta_log') as $name) {
            if (preg_match('/^(\d{20})\.json$/', $name, $matches) === 1) {
                $commits[(int) $matches[1]] = $name;
            }
        }
        ksort($commits);

        foreach ($commits as $version => $name) {
            $this->versions[] = $version;
            $this->apply($version, $storage->read($basePath . '/_delta_log/' . $name));
        }
    }

    public static function open(string $basePath, DeltaStorage $storage): self
    {
        return new self(rtrim($basePath, '/'), $storage);
    }

    /** @return list<int> */
    public function versions(): array
    {
        return $this->versions;
    }

    public function latestVersion(): ?int
    {
        return $this->versions === [] ? null : $this->versions[array_key_last($this->versions)];
    }

    /** @return array<string, mixed> */
    public function protocol(): array
    {
        return $this->protocol;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Data files currently part of the table, keyed by path internally and
     * returned sorted by path.
     *
     * @return list<array<string, mixed>>
     */
    public function activeFiles(): array
    {
        ksort($this->files);

        return array_values($this->files);
    }

    /** @return array<string, mixed>|null */
    public function commitInfo(int $version): ?array
    {
        return $this->commitInfos[$version] ?? null;
    }

    private function apply(int $version, string $contents): void
    {
        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new DeltaLakeException(
                    sprintf('Invalid JSON in Delta log version %d: %s', $version, $e->getMessage()),
                    0,
                    $e,
                );
            }

            if (!is_array($record)) {
                throw new DeltaLakeException(
                    sprintf('Invalid Delta log version %d: expected a JSON object per line.', $version),
                );
            }

            foreach (['protocol', 'metaData', 'add', 'remove', 'commitInfo'] as $action) {
                if (array_key_exists($action, $record) && !is_array($record[$action])) {
                    throw new DeltaLakeException(sprintf(
                        'Invalid Delta log version %d "%s" action: expected an object.',
                        $version,
                        $action,
                    ));
                }
            }

            if (isset($record['protocol'])) {
                $this->protocol = $record['protocol'];
            }
            if (isset($record['metaData'])) {
                $this->metadata = $record['metaData'];
            }
            if (isset($record['add'])) {
                $this->files[self::path($record['add'], 'add', $version)] = $record['add'];
            }
            if (isset($record['remove'])) {
                unset($this->files[self::path($record['remove'], 'remove', $version)]);
            }
            if (isset($record['commitInfo'])) {
                $this->commitInfos[$version] = $record['commitInfo'];
            }
        }
    }

    /** @param array<string, mixed> $action */
    private static function path(array $action, string $type, int $version): string
    {
        $path = $action['path'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new DeltaLakeException(sprintf(
                'Invalid Delta log version %d "%s" action: missing "path".',
                $version,
                $type,
            ));
        }

        return $path;
    }
}
