<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\DeltaLake;

use Erikwang2013\BigData\Driver\DeltaLake\DeltaLog;
use Erikwang2013\BigData\Driver\DeltaLake\Exception\DeltaLakeException;
use Erikwang2013\BigData\Driver\DeltaLake\LocalStorage;
use PHPUnit\Framework\TestCase;

final class DeltaLogTest extends TestCase
{
    private function log(): DeltaLog
    {
        return DeltaLog::open(__DIR__ . '/Fixtures/table', new LocalStorage());
    }

    public function test_versions_sorted_and_non_commit_files_ignored(): void
    {
        self::assertSame([0, 1, 2], $this->log()->versions());
        self::assertSame(2, $this->log()->latestVersion());
    }

    public function test_protocol_and_metadata_are_last_seen(): void
    {
        $log = $this->log();

        self::assertSame(['minReaderVersion' => 1, 'minWriterVersion' => 2], $log->protocol());
        self::assertSame('delta-table-1', $log->metadata()['id']);
        self::assertSame('orders', $log->metadata()['name']);
        self::assertSame(['dt'], $log->metadata()['partitionColumns']);
        self::assertSame('false', $log->metadata()['configuration']['delta.appendOnly']);
    }

    public function test_active_files_replay_adds_and_removes(): void
    {
        $files = $this->log()->activeFiles();

        self::assertSame(
            ['dt=2026-10-30/part-00001.parquet', 'dt=2026-10-30/part-00002.parquet'],
            array_column($files, 'path'),
        );
        self::assertSame(200, $files[0]['size']);
        self::assertSame(['dt' => '2026-10-30'], $files[1]['partitionValues']);
    }

    public function test_commit_info_per_version(): void
    {
        $log = $this->log();

        self::assertSame('WRITE', $log->commitInfo(0)['operation']);
        self::assertSame('DELETE', $log->commitInfo(1)['operation']);
        self::assertSame(2, $log->commitInfo(2)['version']);
        self::assertNull($log->commitInfo(99));
    }

    public function test_invalid_json_throws(): void
    {
        $this->expectException(DeltaLakeException::class);
        $this->expectExceptionMessage('Invalid JSON in Delta log version 0');

        DeltaLog::open(__DIR__ . '/Fixtures/invalid-json', new LocalStorage());
    }

    public function test_add_without_path_throws(): void
    {
        $this->expectException(DeltaLakeException::class);
        $this->expectExceptionMessage('missing "path"');

        DeltaLog::open(__DIR__ . '/Fixtures/invalid-add', new LocalStorage());
    }

    public function test_missing_log_directory_throws(): void
    {
        $this->expectException(DeltaLakeException::class);

        DeltaLog::open(__DIR__ . '/Fixtures/missing', new LocalStorage());
    }
}
