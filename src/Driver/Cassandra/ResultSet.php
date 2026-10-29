<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

final class ResultSet
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $raw
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $rows,
        private readonly array $raw = [],
        private readonly ?string $pagingState = null,
    ) {
    }

    /** @return list<array{name: string, type?: string}> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @return list<array<string, mixed>> */
    public function raw(): array
    {
        return $this->raw;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function pagingState(): ?string
    {
        return $this->pagingState;
    }
}
