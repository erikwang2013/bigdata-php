<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DeltaLake;

final class DeltaSharingResult
{
    public function __construct(
        private readonly array $protocol,
        private readonly array $metadata,
        private readonly array $files,
    ) {
    }

    public function protocol(): array
    {
        return $this->protocol;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function files(): array
    {
        return $this->files;
    }
}
