<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hudi;

/**
 * Read-only view of a Hudi table's metadata: hoodie.properties and the timeline.
 *
 * Instant file names are "{timestamp}.{action}" (0.x) or
 * "{begin}_{completion}.{action}" (1.0) for completed instants, with a
 * trailing ".{state}" for in-flight ones (requested/inflight). When present,
 * the completion timestamp is the instant's effective timestamp.
 */
final class HudiTable
{
    /** @var array<string, string> */
    private array $properties;

    /** @var list<array{timestamp: string, action: string, state: ?string}> */
    private array $timeline;

    private function __construct(string $basePath, HudiStorage $storage)
    {
        $this->properties = self::parseProperties(
            $storage->read($basePath . '/.hoodie/hoodie.properties'),
        );
        $this->timeline = self::parseTimeline(
            $storage->list($basePath . '/.hoodie/timeline'),
        );
    }

    public static function open(string $basePath, HudiStorage $storage): self
    {
        return new self(rtrim($basePath, '/'), $storage);
    }

    /** @return array<string, string> */
    public function properties(): array
    {
        return $this->properties;
    }

    /** @return list<array{timestamp: string, action: string, state: ?string}> */
    public function timeline(): array
    {
        return $this->timeline;
    }

    /** Highest timestamp among completed instants, or null if there are none. */
    public function latestCommit(): ?string
    {
        $latest = null;
        foreach ($this->timeline as $instant) {
            if ($instant['state'] === null && ($latest === null || $instant['timestamp'] > $latest)) {
                $latest = $instant['timestamp'];
            }
        }

        return $latest;
    }

    public function tableName(): ?string
    {
        $name = $this->properties['hoodie.table.name'] ?? '';

        return $name === '' ? null : $name;
    }

    /** @return array<string, string> */
    private static function parseProperties(string $contents): array
    {
        $properties = [];
        foreach (preg_split('/\R/', $contents) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === '!') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $properties[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $properties;
    }

    /** @param list<string> $names */
    private static function parseTimeline(array $names): array
    {
        sort($names);

        $timeline = [];
        foreach ($names as $name) {
            if (preg_match('/^(\d{14})(?:_(\d{14}))?\.([A-Za-z]+)(?:\.([A-Za-z]+))?$/', $name, $matches) !== 1) {
                continue;
            }

            $completion = $matches[2] ?? '';

            $timeline[] = [
                'timestamp' => $completion === '' ? $matches[1] : $completion,
                'action' => $matches[3],
                'state' => $matches[4] ?? null,
            ];
        }

        return $timeline;
    }
}
