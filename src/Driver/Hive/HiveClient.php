<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class HiveClient
{
    private const FETCH_SIZE = 1000;

    private HiveTransport $transport;

    private LoggerInterface $logger;

    private bool $connected = false;

    public function __construct(
        HiveConfig $config,
        ?HiveTransport $transport = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->transport = $transport ?? new ThriftTransport($config);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Builds a client around a caller-supplied transport without going
     * through the BigData factory (which always uses the Thrift transport).
     */
    public static function withTransport(
        HiveConfig $config,
        HiveTransport $transport,
        ?LoggerInterface $logger = null,
    ): self {
        return new self($config, $transport, $logger);
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->logger->debug('Hive connect');
        $this->transport->open();
        $this->connected = true;
    }

    public function execute(string $sql): ResultSet
    {
        $this->connect();
        $this->logger->debug('Hive execute');

        $result = $this->transport->execute($sql);
        $rows = [];
        $raw = [];

        do {
            $batch = $this->transport->fetch(self::FETCH_SIZE);
            $raw[] = $batch;

            foreach ($batch['rows'] as $row) {
                $rows[] = $row;
            }
        } while (($batch['hasMore'] ?? false) === true);

        return new ResultSet($result['columns'], $rows, $raw);
    }

    public function set(string $key, string $value): void
    {
        $this->execute(sprintf('SET %s=%s', $key, $value));
    }

    public function close(): void
    {
        if (!$this->connected) {
            return;
        }

        $this->logger->debug('Hive close');
        $this->transport->close();
        $this->connected = false;
    }
}
