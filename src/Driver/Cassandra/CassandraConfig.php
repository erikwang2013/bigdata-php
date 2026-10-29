<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

final class CassandraConfig
{
    /**
     * @param list<string> $contactPoints "host:port" entries
     */
    public function __construct(
        public array $contactPoints,
        public string $keyspace = '',
        public string $username = '',
        public string $password = '',
        public int $protocolVersion = 4,
        public float $connectTimeout = 5.0,
        public float $timeout = 10.0,
        public bool $tls = false,
        public bool $verifySsl = true,
        /**
         * "random" or "round_robin". php-cassandra v1.2 has no token-aware
         * replica routing, so this only changes failover order, not partitioning.
         */
        public string $nodeSelection = 'random',
    ) {
        if (!in_array($this->protocolVersion, [3, 4, 5], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Cassandra config "protocol_version" must be 3, 4 or 5, got %d.',
                $this->protocolVersion,
            ));
        }

        if (!in_array($this->nodeSelection, ['random', 'round_robin'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Cassandra config "node_selection" must be "random" or "round_robin", got "%s".',
                $this->nodeSelection,
            ));
        }

        foreach ($this->contactPoints as $point) {
            if (substr_count($point, ':') > 1 && !str_starts_with($point, '[')) {
                throw new \InvalidArgumentException(sprintf(
                    'Cassandra contact point "%s" looks like an unbracketed IPv6 address; use "[::1]:9042" notation.',
                    $point,
                ));
            }
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $points = $config['contact_points'] ?? $config['contactPoints'] ?? null;
        if (!is_array($points) || $points === []) {
            throw new \InvalidArgumentException('Cassandra config requires a non-empty "contact_points" array.');
        }

        return new self(
            contactPoints: array_values(array_map('strval', $points)),
            keyspace: (string) ($config['keyspace'] ?? ''),
            username: (string) ($config['username'] ?? ''),
            password: (string) ($config['password'] ?? ''),
            protocolVersion: (int) ($config['protocol_version'] ?? 4),
            connectTimeout: (float) ($config['connect_timeout'] ?? 5.0),
            timeout: (float) ($config['timeout'] ?? 10.0),
            tls: (bool) ($config['tls'] ?? false),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            nodeSelection: (string) ($config['node_selection'] ?? 'random'),
        );
    }
}
