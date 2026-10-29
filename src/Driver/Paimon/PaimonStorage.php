<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Paimon;

/**
 * Read-only access to the files a Paimon table keeps under schema/ and snapshot/.
 */
interface PaimonStorage
{
    /**
     * File names (not full paths) directly inside the directory at $path.
     *
     * @return list<string>
     */
    public function list(string $path): array;

    /**
     * Full contents of the file at $path.
     */
    public function read(string $path): string;
}
