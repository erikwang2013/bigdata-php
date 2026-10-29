<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Druid;

final class ResultSet
{
    /**
     * @param list<string>               $columns Derived from the first row's keys.
     * @param list<array<string, mixed>> $rows
     * @param array<mixed>               $raw Decoded response body.
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
