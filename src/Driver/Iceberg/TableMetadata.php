<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Iceberg;

/**
 * Metadata of an Iceberg table as returned by loadTable / createTable.
 * Pointers only: no data or manifest file is ever read by this package.
 */
final class TableMetadata
{
    public function __construct(
        private string $location,
        private array $schema,
        private array $partitionSpec,
        private array $snapshots,
        private array $properties,
        private ?int $currentSnapshotId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $currentSnapshotId = isset($data['current-snapshot-id']) ? (int) $data['current-snapshot-id'] : null;

        return new self(
            location: (string) ($data['location'] ?? ''),
            // v1 metadata carries the active schema/spec directly; v2 keeps lists plus an id.
            schema: (array) ($data['schema'] ?? self::v2Schema($data) ?? []),
            partitionSpec: (array) ($data['partition-spec'] ?? self::v2PartitionSpec($data) ?? []),
            snapshots: (array) ($data['snapshots'] ?? []),
            properties: (array) ($data['properties'] ?? []),
            // Iceberg uses -1 to mean "no current snapshot".
            currentSnapshotId: $currentSnapshotId === null || $currentSnapshotId < 0 ? null : $currentSnapshotId,
        );
    }

    private static function v2Schema(array $data): ?array
    {
        $schemaId = (int) ($data['current-schema-id'] ?? -1);

        foreach ($data['schemas'] ?? [] as $schema) {
            if (is_array($schema) && (int) ($schema['schema-id'] ?? -1) === $schemaId) {
                return $schema;
            }
        }

        return null;
    }

    private static function v2PartitionSpec(array $data): ?array
    {
        $specId = (int) ($data['default-spec-id'] ?? -1);

        foreach ($data['partition-specs'] ?? [] as $spec) {
            if (is_array($spec) && (int) ($spec['spec-id'] ?? -1) === $specId) {
                return (array) ($spec['fields'] ?? []);
            }
        }

        return null;
    }

    public function location(): string
    {
        return $this->location;
    }

    public function schema(): array
    {
        return $this->schema;
    }

    public function partitionSpec(): array
    {
        return $this->partitionSpec;
    }

    public function snapshots(): array
    {
        return $this->snapshots;
    }

    public function properties(): array
    {
        return $this->properties;
    }

    public function currentSnapshotId(): ?int
    {
        return $this->currentSnapshotId;
    }
}
