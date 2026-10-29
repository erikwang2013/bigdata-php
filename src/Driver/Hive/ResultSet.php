<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive;

final class ResultSet
{
    /**
     * @param list<array{name: string, type: string}>              $columns
     * @param list<list<mixed>>                                   $rows
     * @param list<array{rows: list<list<mixed>>, hasMore: bool}> $raw One entry per fetched batch.
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $rows,
        private readonly array $raw,
    ) {
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function rows(): array
    {
        return $this->rows;
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
