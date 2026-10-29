<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Airflow\Exception;

class RequestException extends AirflowException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly string $body = '',
    ) {
        parent::__construct($message);
    }
}
