<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

final class CassandraConfig
{
    /**
     * @param list<string> $contactPoints "host:port" entries
     */
    public function __construct(
        public readonly array $contactPoints,
        public readonly string $keyspace = '',
        public readonly string $username = '',
        public readonly string $password = '',
        public readonly int $protocolVersion = 4,
        public readonly float $connectTimeout = 5.0,
        public readonly float $timeout = 10.0,
        public readonly bool $tls = false,
        public readonly bool $verifySsl = true,
    ) {
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
        );
    }
}
