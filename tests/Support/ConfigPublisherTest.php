<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Support;

use Erikwang2013\BigData\Support\ConfigPublisher;
use PHPUnit\Framework\TestCase;

final class ConfigPublisherTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/bigdata-config-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if ($this->directory === '' || !is_dir($this->directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_copies_into_a_missing_directory(): void
    {
        $source = $this->directory . '/template.php';
        $destination = $this->directory . '/config/bigdata.php';
        file_put_contents($source, "<?php return ['default' => 'trino'];\n");

        self::assertTrue(ConfigPublisher::publishIfMissing($source, $destination));
        self::assertSame(
            "<?php return ['default' => 'trino'];\n",
            file_get_contents($destination),
        );
    }

    public function test_never_overwrites_an_existing_config(): void
    {
        $source = $this->directory . '/template.php';
        $destination = $this->directory . '/bigdata.php';
        file_put_contents($source, "<?php return [];\n");
        file_put_contents($destination, "<?php return ['custom' => true];\n");

        self::assertFalse(ConfigPublisher::publishIfMissing($source, $destination));
        self::assertSame("<?php return ['custom' => true];\n", file_get_contents($destination));
    }

    public function test_missing_source_is_ignored(): void
    {
        self::assertFalse(ConfigPublisher::publishIfMissing(
            $this->directory . '/missing.php',
            $this->directory . '/bigdata.php',
        ));
        self::assertFileDoesNotExist($this->directory . '/bigdata.php');
    }
}
