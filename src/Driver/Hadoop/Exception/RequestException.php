<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hadoop\Exception;

class RequestException extends HadoopException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly string $body = '',
    ) {
        parent::__construct($message);
    }
}
