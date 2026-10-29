<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Kylin;

final class ResultSet
{
    /**
     * @param list<array<string, mixed>> $columns Kylin columnMeta entries (name, label, dataType).
     * @param list<list<mixed>>          $rows
     * @param list<array<string, mixed>> $raw One entry per HTTP response returned by Kylin.
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
