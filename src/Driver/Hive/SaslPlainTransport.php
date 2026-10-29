<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hive;

use Thrift\Exception\TTransportException;
use Thrift\Transport\TTransport;

/**
 * Client-side SASL PLAIN transport for HiveServer2.
 *
 * apache/thrift's PHP runtime has no SASL transport, so this implements the
 * Thrift SASL negotiation framing (status byte + big-endian length) and the
 * 4-byte length framing used for RPC messages once QOP is "auth".
 */
final class SaslPlainTransport extends TTransport
{
    private const START = 0x01;

    private const OK = 0x02;

    private const BAD = 0x03;

    private const ERROR = 0x04;

    private const COMPLETE = 0x05;

    private string $writeBuffer = '';

    private string $readBuffer = '';

    public function __construct(
        private TTransport $transport,
        private string $username,
        private string $password,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->transport->isOpen();
    }

    public function open(): void
    {
        if (!$this->transport->isOpen()) {
            $this->transport->open();
        }

        $this->sendMessage(self::START, 'PLAIN');
        $this->sendMessage(self::OK, "\0" . $this->username . "\0" . $this->password);

        while (true) {
            [$status, $payload] = $this->receiveMessage();
            if ($status === self::COMPLETE) {
                return;
            }
            if ($status !== self::OK) {
                throw new TTransportException('Unexpected SASL status ' . $status);
            }
            $this->sendMessage(self::OK, '');
        }
    }

    public function close(): void
    {
        $this->transport->close();
    }

    public function read(int $len): string
    {
        while (strlen($this->readBuffer) < $len) {
            $this->readFrame();
        }

        $data = substr($this->readBuffer, 0, $len);
        $this->readBuffer = substr($this->readBuffer, $len);

        return $data;
    }

    public function write(string $buf): void
    {
        $this->writeBuffer .= $buf;
    }

    public function flush(): void
    {
        if ($this->writeBuffer === '') {
            return;
        }

        $this->transport->write(pack('N', strlen($this->writeBuffer)) . $this->writeBuffer);
        $this->writeBuffer = '';
        $this->transport->flush();
    }

    private function sendMessage(int $status, string $payload): void
    {
        $this->transport->write(chr($status) . pack('N', strlen($payload)) . $payload);
        $this->transport->flush();
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function receiveMessage(): array
    {
        $header = $this->transport->readAll(5);
        $status = ord($header[0]);
        $length = unpack('N', substr($header, 1, 4))[1];
        $payload = $length > 0 ? $this->transport->readAll($length) : '';

        if ($status === self::BAD || $status === self::ERROR) {
            throw new TTransportException('SASL negotiation failed: ' . $payload);
        }

        return [$status, $payload];
    }

    private function readFrame(): void
    {
        $length = unpack('N', $this->transport->readAll(4))[1];
        $this->readBuffer .= $length > 0 ? $this->transport->readAll($length) : '';
    }
}
