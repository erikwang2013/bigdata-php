<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Oozie\Exception;

class RequestException extends OozieException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly string $body = '',
    ) {
        parent::__construct($message);
    }
}
