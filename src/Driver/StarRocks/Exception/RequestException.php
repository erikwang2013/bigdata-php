<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\StarRocks\Exception;

class RequestException extends StarRocksException
{
    public function __construct(
        string $message,
        public ?int $statusCode = null,
        public string $body = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
