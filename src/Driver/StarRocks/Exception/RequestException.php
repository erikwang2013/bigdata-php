<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\StarRocks\Exception;

class RequestException extends StarRocksException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly string $body = '',
    ) {
        parent::__construct($message);
    }
}
