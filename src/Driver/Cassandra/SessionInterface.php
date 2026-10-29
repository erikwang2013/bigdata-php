<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

interface SessionInterface
{
    public function connect(): void;

    public function query(string $cql, array $params, int $consistency, ?int $pageSize = null, ?string $pagingState = null): ResultSet;

    public function prepare(string $cql): PreparedStatement;

    public function execute(PreparedStatement $statement, array $params, int $consistency, ?int $pageSize = null, ?string $pagingState = null): ResultSet;

    public function close(): void;
}
