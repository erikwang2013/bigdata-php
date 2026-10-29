<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive\Exception;

class RequestException extends HiveException
{
    public function __construct(
        string $message,
        public readonly ?string $sqlState = null,
        public readonly ?int $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
