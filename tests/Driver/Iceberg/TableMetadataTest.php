<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Iceberg;

use Erikwang2013\BigData\Driver\Iceberg\TableMetadata;
use PHPUnit\Framework\TestCase;

final class TableMetadataTest extends TestCase
{
    public function test_parses_metadata_fields(): void
    {
        $schema = [
            'type' => 'struct',
            'schema-id' => 0,
            'fields' => [['id' => 1, 'name' => 'id', 'required' => true, 'type' => 'long']],
        ];
        $partitionSpec = [
            ['field-id' => 1000, 'source-id' => 1, 'name' => 'id', 'transform' => 'bucket[16]'],
        ];
        $snapshots = [
            ['snapshot-id' => 42, 'timestamp-ms' => 1700000001000, 'manifest-list' => 's3://bucket/snap-42.avro'],
        ];

        $metadata = TableMetadata::fromArray([
            'location' => 's3://bucket/warehouse/analytics/events',
            'schema' => $schema,
            'partition-spec' => $partitionSpec,
            'snapshots' => $snapshots,
            'properties' => ['owner' => 'data-team'],
            'current-snapshot-id' => 42,
        ]);

        self::assertSame('s3://bucket/warehouse/analytics/events', $metadata->location());
        self::assertSame($schema, $metadata->schema());
        self::assertSame($partitionSpec, $metadata->partitionSpec());
        self::assertSame($snapshots, $metadata->snapshots());
        self::assertSame(['owner' => 'data-team'], $metadata->properties());
        self::assertSame(42, $metadata->currentSnapshotId());
    }

    public function test_parses_v2_metadata_shape(): void
    {
        $schema = [
            'type' => 'struct',
            'schema-id' => 1,
            'fields' => [['id' => 1, 'name' => 'id', 'required' => true, 'type' => 'long']],
        ];
        $fields = [
            ['field-id' => 1000, 'source-id' => 1, 'name' => 'id', 'transform' => 'bucket[16]'],
        ];

        $metadata = TableMetadata::fromArray([
            'format-version' => 2,
            'location' => 's3://bucket/table',
            'current-schema-id' => 1,
            'schemas' => [
                ['type' => 'struct', 'schema-id' => 0, 'fields' => []],
                $schema,
            ],
            'default-spec-id' => 2,
            'partition-specs' => [
                ['spec-id' => 0, 'fields' => []],
                ['spec-id' => 2, 'fields' => $fields],
            ],
        ]);

        self::assertSame($schema, $metadata->schema());
        self::assertSame($fields, $metadata->partitionSpec());
    }

    public function test_empty_metadata_uses_defaults(): void
    {
        $metadata = TableMetadata::fromArray(['location' => 's3://bucket/table']);

        self::assertSame([], $metadata->schema());
        self::assertSame([], $metadata->partitionSpec());
        self::assertSame([], $metadata->snapshots());
        self::assertSame([], $metadata->properties());
        self::assertNull($metadata->currentSnapshotId());
    }

    public function test_minus_one_current_snapshot_id_is_null(): void
    {
        $metadata = TableMetadata::fromArray([
            'location' => 's3://bucket/table',
            'current-snapshot-id' => -1,
        ]);

        self::assertNull($metadata->currentSnapshotId());
    }
}
