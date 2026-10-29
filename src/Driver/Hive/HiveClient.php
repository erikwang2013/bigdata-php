<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive;

final class HiveClient
{
    private const FETCH_SIZE = 1000;

    private readonly HiveTransport $transport;

    private bool $connected = false;

    public function __construct(HiveConfig $config, ?HiveTransport $transport = null)
    {
        $this->transport = $transport ?? new ThriftTransport($config);
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->transport->open();
        $this->connected = true;
    }

    public function execute(string $sql): ResultSet
    {
        $this->connect();

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

        $this->transport->close();
        $this->connected = false;
    }
}
