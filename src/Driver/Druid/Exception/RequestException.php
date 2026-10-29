<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Druid\Exception;

class RequestException extends DruidException
{
    public function __construct(
        string $message,
        public ?int $statusCode = null,
        public string $body = '',
    ) {
        parent::__construct($message);
    }
}
