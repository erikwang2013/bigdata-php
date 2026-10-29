<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Paimon;

use Erikwang2013\BigData\Driver\Paimon\LocalStorage;
use Erikwang2013\BigData\Driver\Paimon\PaimonTable;
use PHPUnit\Framework\TestCase;

final class PaimonTableTest extends TestCase
{
    private function table(): PaimonTable
    {
        return PaimonTable::open(__DIR__ . '/Fixtures/table', new LocalStorage());
    }

    public function test_schema_reads_highest_schema_file(): void
    {
        $schema = $this->table()->schema();

        self::assertSame(1, $schema['id']);
        self::assertSame(['id'], $schema['primaryKeys']);
        self::assertSame(['dt'], $schema['partitionKeys']);
        self::assertSame(['id', 'name', 'dt'], array_column($schema['fields'], 'name'));
    }

    public function test_snapshots_sorted_by_id(): void
    {
        $table = $this->table();
        $snapshots = $table->snapshots();

        self::assertSame([1, 2, 10], array_column($snapshots, 'id'));
        self::assertSame([0, 1, 1], array_column($snapshots, 'schemaId'));
        self::assertSame('etl', $snapshots[2]['commitUser']);
        self::assertSame($snapshots[2], $table->latestSnapshot());
    }

    public function test_options_extracted_from_schema(): void
    {
        self::assertSame(
            [
                'bucket' => '2',
                'file.format' => 'parquet',
                'snapshot.num-retained.max' => '10',
            ],
            $this->table()->options(),
        );
    }
}
