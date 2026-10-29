<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Paimon;

use Erikwang2013\BigData\Driver\Paimon\Exception\PaimonException;

final class LocalStorage implements PaimonStorage
{
    public function list(string $path): array
    {
        $entries = @scandir($path);
        if ($entries === false) {
            throw new PaimonException(sprintf('Unable to list directory "%s".', $path));
        }

        return array_values(array_diff($entries, ['.', '..']));
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new PaimonException(sprintf('Unable to read file "%s".', $path));
        }

        return $contents;
    }
}
