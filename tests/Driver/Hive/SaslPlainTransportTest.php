<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hive;

use Erikwang2013\BigData\Driver\Hive\SaslPlainTransport;
use PHPUnit\Framework\TestCase;
use Thrift\Exception\TTransportException;
use Thrift\Transport\TTransport;

final class SaslPlainTransportTest extends TestCase
{
    private const START = 0x01;

    private const OK = 0x02;

    private const BAD = 0x03;

    private const COMPLETE = 0x05;

    public function test_open_sends_plain_start_and_initial_response(): void
    {
        $server = new ScriptedTransport($this->message(self::COMPLETE, ''));
        $transport = new SaslPlainTransport($server, 'alice', 's3cret');

        $transport->open();

        $expected = $this->message(self::START, 'PLAIN')
            . $this->message(self::OK, "\0alice\0s3cret");
        self::assertSame($expected, $server->written);
        self::assertTrue($transport->isOpen());
    }

    public function test_data_is_length_framed_after_handshake(): void
    {
        $server = new ScriptedTransport(
            $this->message(self::COMPLETE, '')
            . pack('N', 2) . 'ab'
            . pack('N', 2) . 'cd',
        );
        $transport = new SaslPlainTransport($server, 'alice', 's3cret');
        $transport->open();

        $transport->write('hello');
        $transport->flush();

        self::assertStringEndsWith(pack('N', 5) . 'hello', $server->written);
        self::assertSame('abc', $transport->read(3));
        self::assertSame('d', $transport->read(1));
    }

    public function test_negotiation_failure_throws(): void
    {
        $server = new ScriptedTransport($this->message(self::BAD, 'nope'));
        $transport = new SaslPlainTransport($server, 'alice', 'wrong');

        $this->expectException(TTransportException::class);
        $this->expectExceptionMessage('nope');
        $transport->open();
    }

    public function test_close_closes_underlying_transport(): void
    {
        $server = new ScriptedTransport($this->message(self::COMPLETE, ''));
        $transport = new SaslPlainTransport($server, 'alice', 's3cret');
        $transport->open();

        $transport->close();

        self::assertTrue($server->closed);
    }

    private function message(int $status, string $payload): string
    {
        return chr($status) . pack('N', strlen($payload)) . $payload;
    }
}

final class ScriptedTransport extends TTransport
{
    public string $written = '';

    public bool $opened = false;

    public bool $closed = false;

    public function __construct(private string $input = '')
    {
    }

    public function isOpen(): bool
    {
        return $this->opened;
    }

    public function open(): void
    {
        $this->opened = true;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function read(int $len): string
    {
        if ($this->input === '') {
            throw new TTransportException('End of scripted input', TTransportException::END_OF_FILE);
        }

        $chunk = substr($this->input, 0, $len);
        $this->input = substr($this->input, $len);

        return $chunk;
    }

    public function write(string $buf): void
    {
        $this->written .= $buf;
    }

    public function flush(): void
    {
    }
}
