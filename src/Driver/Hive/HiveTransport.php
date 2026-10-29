<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive;

interface HiveTransport
{
    public function open(): void;

    /**
     * Runs a statement and returns its metadata; rows are read with fetch().
     *
     * @return array{columns: list<array{name: string, type: string}>, handle: mixed}
     */
    public function execute(string $sql): array;

    /**
     * @return array{rows: list<list<mixed>>, hasMore: bool}
     */
    public function fetch(int $maxRows): array;

    public function close(): void;
}
