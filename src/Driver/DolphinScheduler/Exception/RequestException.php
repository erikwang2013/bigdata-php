<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DolphinScheduler\Exception;

class RequestException extends DolphinSchedulerException
{
    public function __construct(
        string $message,
        public ?int $statusCode = null,
        public string $body = '',
    ) {
        parent::__construct($message);
    }
}
