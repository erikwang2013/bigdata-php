<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Doris\Exception;

class RequestException extends DorisException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly string $body = '',
    ) {
        parent::__construct($message);
    }
}
