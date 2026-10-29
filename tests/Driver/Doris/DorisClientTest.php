<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Doris;

use Erikwang2013\BigData\Driver\Doris\DorisClient;
use Erikwang2013\BigData\Driver\Doris\DorisConfig;
use Erikwang2013\BigData\Driver\Doris\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Doris\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DorisClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses): DorisClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new DorisClient(
            DorisConfig::fromArray(['host' => 'doris.local', 'database' => 'demo']),
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
        self::assertSame('http://doris.local:8030/api/demo/orders/_stream_load', (string) $request->getUri());
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

    public function test_sql_error_maps_to_request_exception(): void
    {
        $error = new \PDOException('SQLSTATE[42S02]: Base table or view not found');
        $error->errorInfo = ['42S02', 1146, 'Table missing'];

        try {
            $this->pdoClient($error)->query('SELECT * FROM missing');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame($error, $e->getPrevious());
        }
    }

    public function test_connection_error_maps_to_connection_exception(): void
    {
        $error = new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
        $error->errorInfo = ['HY000', 2002, 'Connection refused'];

        $this->expectException(ConnectionException::class);
        $this->pdoClient($error)->execute('DELETE FROM orders');
    }

    public function test_connection_sqlstate_maps_to_connection_exception(): void
    {
        $error = new \PDOException('SQLSTATE[08006] Unable to connect');
        $error->errorInfo = ['08006', 7, 'Unable to connect'];

        try {
            $this->pdoClient($error)->query('SELECT 1');
            self::fail('Expected ConnectionException.');
        } catch (ConnectionException $e) {
            self::assertSame($error, $e->getPrevious());
        }
    }

    public function test_execute_sql_error_maps_to_request_exception(): void
    {
        $error = new \PDOException('SQLSTATE[42S02]: Base table or view not found');
        $error->errorInfo = ['42S02', 1146, 'Table missing'];

        try {
            $this->pdoClient($error)->execute('DELETE FROM missing');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame($error, $e->getPrevious());
        }
    }

    public function test_pdo_creation_failure_maps_to_connection_exception(): void
    {
        // Port 1 is closed on a normal host, so lazy PDO creation fails fast.
        $client = new DorisClient(DorisConfig::fromArray([
            'host' => '127.0.0.1',
            'database' => 'demo',
            'port' => 1,
        ]));

        try {
            $client->query('SELECT 1');
            self::fail('Expected ConnectionException.');
        } catch (ConnectionException $e) {
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
            self::assertStringContainsString('streamLoad()', $e->getMessage());
        }
    }

    private function pdoClient(\PDOException $error): DorisClient
    {
        return new DorisClient(
            DorisConfig::fromArray(['host' => 'doris.local', 'database' => 'demo']),
            new FakePdo($error),
        );
    }
}

final class FakePdo extends \PDO
{
    public function __construct(private \PDOException $error)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        throw $this->error;
    }
}
