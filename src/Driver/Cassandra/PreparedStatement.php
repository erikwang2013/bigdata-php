<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

final class PreparedStatement
{
    /** @param mixed $driverResult Opaque driver PreparedResult. */
    public function __construct(
        private string $cql,
        private mixed $driverResult,
        private array $columns = [],
    ) {
    }

    public function id(): string
    {
        $data = is_object($this->driverResult) && method_exists($this->driverResult, 'getPreparedData')
            ? $this->driverResult->getPreparedData()
            : null;
        $id = is_object($data) && isset($data->id) && is_string($data->id) ? $data->id : null;

        return $id !== null ? bin2hex($id) : hash('sha256', $this->cql);
    }

    public function query(): string
    {
        return $this->cql;
    }

    /**
     * Bind-marker columns from the driver's prepared metadata (PREPARED result
     * kind), or an empty array when the driver exposes none.
     *
     * @return list<array{name: string, type?: string}>
     */
    public function columns(): array
    {
        if ($this->columns !== []) {
            return $this->columns;
        }

        $data = is_object($this->driverResult) && method_exists($this->driverResult, 'getPreparedData')
            ? $this->driverResult->getPreparedData()
            : null;

        $markers = is_object($data) && isset($data->prepareMetadata)
            ? ($data->prepareMetadata->bindMarkers ?? [])
            : [];

        if (!is_array($markers)) {
            return [];
        }

        $columns = [];
        foreach ($markers as $marker) {
            if (!is_object($marker)) {
                continue;
            }

            $column = ['name' => (string) ($marker->name ?? '')];
            $type = $marker->type->type ?? null;
            if (is_object($type) && isset($type->name)) {
                $column['type'] = strtolower((string) $type->name);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /** @internal */
    public function driverResult(): mixed
    {
        return $this->driverResult;
    }
}
