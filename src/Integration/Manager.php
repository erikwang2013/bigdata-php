<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration;

use Erikwang2013\BigData\BigData;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Framework-agnostic driver manager. Every framework adapter resolves this
 * class from its container; the config shape is the same everywhere:
 *
 *     [
 *         'default' => 'trino',
 *         'drivers' => [
 *             'trino' => ['host' => '127.0.0.1', 'port' => 8080],
 *             'airflow' => ['baseUrl' => 'http://127.0.0.1:8080'],
 *         ],
 *     ]
 */
final class Manager
{
    /** @var array<string, object> */
    private array $instances = [];

    private string $default;

    public function __construct(
        private array $config = [],
        private ?ClientInterface $http = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->default = (string) ($config['default'] ?? 'trino');
    }

    public function driver(?string $name = null): object
    {
        $name = $name ?? $this->default;

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $drivers = $this->config['drivers'] ?? [];

        if (!isset($drivers[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'BigData driver [%s] is not configured. Configured drivers: %s.',
                $name,
                $this->drivers() === [] ? '(none)' : implode(', ', $this->drivers()),
            ));
        }

        return $this->instances[$name] = BigData::driver(
            $name,
            (array) $drivers[$name],
            $this->http,
            $this->logger,
        );
    }

    /** @return list<string> Names configured under "drivers". */
    public function drivers(): array
    {
        return array_keys($this->config['drivers'] ?? []);
    }

    /** @return list<string> Configured drivers whose optional dependencies are installed. */
    public function available(): array
    {
        return array_values(array_intersect($this->drivers(), BigData::available()));
    }
}
