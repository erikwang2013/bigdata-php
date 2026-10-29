<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\StarRocks;

use Erikwang2013\BigData\Driver\StarRocks\StarRocksClient;
use Erikwang2013\BigData\Driver\StarRocks\StarRocksConfig;
use Erikwang2013\BigData\Driver\StarRocks\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class StarRocksClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses): StarRocksClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new StarRocksClient(
            StarRocksConfig::fromArray(['host' => 'starrocks.local', 'database' => 'demo']),
            null,
            new Client(['handler' => $stack]),
        );
    }

    public function test_stream_load_sends_label_and_auth(): void
    {
        $client = $this->client([new Response(200, [], json_encode(['Status' => 'Success']))]);

        $client->streamLoad('orders', '{"id":1}');

        $request = $this->history[0]['request'];

        self::assertSame('PUT', $request->getMethod());
        self::assertSame('http://starrocks.local:8030/api/demo/orders/_stream_load', (string) $request->getUri());
        self::assertSame('Basic ' . base64_encode('root:'), $request->getHeaderLine('Authorization'));
        self::assertSame('json', $request->getHeaderLine('format'));
        self::assertStringStartsWith('php_', $request->getHeaderLine('label'));
        self::assertSame('100-continue', $request->getHeaderLine('Expect'));
        self::assertSame('{"id":1}', (string) $request->getBody());
    }

    public function test_stream_load_accepts_header_options(): void
    {
        $client = $this->client([new Response(200, [], json_encode(['Status' => 'Success']))]);

        $client->streamLoad('orders', 'id,name', [
            'label' => 'batch_42',
            'format' => 'csv',
            'max_filter_ratio' => '0.1',
        ]);

        $request = $this->history[0]['request'];

        self::assertSame('batch_42', $request->getHeaderLine('label'));
        self::assertSame('csv', $request->getHeaderLine('format'));
        self::assertSame('0.1', $request->getHeaderLine('max_filter_ratio'));
    }

    public function test_stream_load_success_status(): void
    {
        $client = $this->client([new Response(200, [], json_encode([
            'Status' => 'Success',
            'Message' => 'OK',
            'NumberLoadedRows' => 2,
        ]))]);

        $result = $client->streamLoad('orders', '{"id":1}');

        self::assertSame('Success', $result['Status']);
        self::assertSame('OK', $result['Message']);
        self::assertSame(2, $result['NumberLoadedRows']);
    }

    public function test_stream_load_error_status_throws(): void
    {
        $client = $this->client([new Response(200, [], json_encode([
            'Status' => 'Fail',
            'Message' => 'type mismatch on column id',
        ]))]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('type mismatch on column id');
        $client->streamLoad('orders', '{"id":"x"}');
    }

    public function test_stream_load_http_error_throws_request_exception(): void
    {
        $client = $this->client([new Response(500, [], 'backend down')]);

        try {
            $client->streamLoad('orders', '{"id":1}');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(500, $e->statusCode);
            self::assertSame('backend down', $e->body);
        }
    }
}
