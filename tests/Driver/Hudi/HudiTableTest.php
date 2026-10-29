<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hudi;

use Erikwang2013\BigData\Driver\Hudi\HudiTable;
use Erikwang2013\BigData\Driver\Hudi\LocalStorage;
use PHPUnit\Framework\TestCase;

final class HudiTableTest extends TestCase
{
    private function table(): HudiTable
    {
        return HudiTable::open(__DIR__ . '/Fixtures/table', new LocalStorage());
    }

    public function test_properties_parsed(): void
    {
        $table = $this->table();

        self::assertSame('hudi_table', $table->tableName());
        self::assertSame('hudi_table', $table->properties()['hoodie.table.name']);
        self::assertSame('MOR', $table->properties()['hoodie.table.type']);
        self::assertSame('id', $table->properties()['hoodie.datasource.write.recordkey.field']);
        self::assertCount(3, $table->properties());
    }

    public function test_timeline_sorted_and_actions_parsed(): void
    {
        $timeline = $this->table()->timeline();

        self::assertSame(
            [
                '20231231000000',
                '20240101000000',
                '20240101000000',
                '20240101000005',
                '20240101000005',
                '20240102000000',
                '20240103000000',
            ],
            array_column($timeline, 'timestamp'),
        );
        self::assertSame(
            ['commit', 'commit', 'commit', 'commit', 'commit', 'deltacommit', 'deltacommit'],
            array_column($timeline, 'action'),
        );
        self::assertSame(
            [null, null, 'requested', null, 'requested', 'requested', 'inflight'],
            array_column($timeline, 'state'),
        );
    }

    public function test_latest_commit_uses_1_0_completion_time_and_ignores_inflight_and_requested(): void
    {
        self::assertSame('20240101000005', $this->table()->latestCommit());
    }
}
