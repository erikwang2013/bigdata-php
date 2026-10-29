<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

final class PreparedStatement
{
    /** @param mixed $driverResult Opaque driver PreparedResult. */
    public function __construct(
        private readonly string $cql,
        private readonly mixed $driverResult,
        private readonly array $columns = [],
    ) {
    }

    public function id(): string
    {
        $data = method_exists($this->driverResult, 'getPreparedData')
            ? $this->driverResult->getPreparedData()
            : null;
        $id = is_object($data) && isset($data->id) && is_string($data->id) ? $data->id : null;

        return $id !== null ? bin2hex($id) : hash('sha256', $this->cql);
    }

    public function query(): string
    {
        return $this->cql;
    }

    /** @return array */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @internal */
    public function driverResult(): mixed
    {
        return $this->driverResult;
    }
}
