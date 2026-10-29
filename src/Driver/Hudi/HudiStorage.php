<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hudi;

/**
 * Read-only access to the files a Hudi table keeps under .hoodie/.
 */
interface HudiStorage
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
