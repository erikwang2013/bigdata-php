<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\StarRocks;

final class ResultSet
{
    /**
     * @param list<string>               $columns Column names, taken from the first row.
     * @param list<array<string, mixed>> $rows    Associative rows (PDO::FETCH_ASSOC).
     * @param list<array<string, mixed>> $raw     Rows as returned by PDO, kept for parity with the HTTP clients.
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
