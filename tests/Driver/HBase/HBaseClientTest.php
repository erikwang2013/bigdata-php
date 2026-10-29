<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\HBase;

use Erikwang2013\BigData\Exception\RequestException;
use Erikwang2013\BigData\Driver\HBase\HBaseClient;
use Erikwang2013\BigData\Driver\HBase\HBaseConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class HBaseClientTest extends TestCase
{
    private const ROW_RESPONSE = '{"Row":[{"key":"cm93MQ==","Cell":[{"column":"Y2Y6YQ==","timestamp":1,"$":"dmFsdWU="}]}]}';

    private array $history = [];

    private function client(array $responses, ?LoggerInterface $logger = null): HBaseClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new HBaseClient(
            HBaseConfig::fromArray([
                'baseUrl' => 'http://hbase.local:8080',
                'username' => 'alice',
                'password' => 'secret',
            ]),
            new Client(['handler' => $stack]),
            $logger,
        );
    }

    private function requestPath(int $index): string
    {
        return rawurldecode($this->history[$index]['request']->getUri()->getPath());
    }

    public function test_row_and_cells_are_base64_encoded(): void
    {
        $client = $this->client([new Response(200)]);
        $client->put('users', 'row1', ['cf:a' => 'value']);

        $request = $this->history[0]['request'];

        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/users/' . base64_encode('row1'), $this->requestPath(0));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $body = json_decode((string) $request->getBody(), true);
        self::assertSame(base64_encode('row1'), $body['Row'][0]['key']);
        self::assertSame(base64_encode('cf:a'), $body['Row'][0]['Cell'][0]['column']);
        self::assertSame(base64_encode('value'), $body['Row'][0]['Cell'][0]['$']);
    }

    public function test_put_body_shape(): void
    {
        $client = $this->client([new Response(200)]);
        $client->put('users', 'row1', ['cf:a' => 'value', 'cf:b' => 'second']);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);

        self::assertSame([
            'Row' => [[
                'key' => 'cm93MQ==',
                'Cell' => [
                    ['column' => 'Y2Y6YQ==', '$' => 'dmFsdWU='],
                    ['column' => 'Y2Y6Yg==', '$' => 'c2Vjb25k'],
                ],
            ]],
        ], $body);
    }

    public function test_get_parses_cells(): void
    {
        $client = $this->client([new Response(200, [], self::ROW_RESPONSE)]);
        $cells = $client->get('users', 'row1', ['cf:a']);

        self::assertSame(['cf:a' => 'value'], $cells);
        self::assertSame(
            '/users/' . base64_encode('row1') . '/' . base64_encode('cf:a'),
            $this->requestPath(0),
        );
    }

    public function test_empty_value_preserved_as_empty_string(): void
    {
        $client = $this->client([
            new Response(200, [], '{"Row":[{"key":"cm93MQ==","Cell":[{"column":"Y2Y6YQ==","timestamp":1,"$":""}]}]}'),
        ]);

        self::assertSame(['cf:a' => ''], $client->get('users', 'row1'));
    }

    public function test_scanner_create_get_delete_flow(): void
    {
        $client = $this->client([
            new Response(201, ['Location' => 'http://hbase.local:8080/users/scanner/scan42']),
            new Response(200, [], self::ROW_RESPONSE),
            new Response(200),
        ]);

        $scanner = $client->scannerCreate('users', ['startRow' => 'row1', 'endRow' => 'row9']);
        self::assertSame('/users/scanner/scan42', $scanner);

        self::assertSame(
            [['key' => 'row1', 'cells' => ['cf:a' => 'value']]],
            $client->scannerGet($scanner, 25),
        );

        $client->scannerDelete($scanner);

        $create = $this->history[0]['request'];
        self::assertSame('POST', $create->getMethod());
        self::assertSame('/users/scanner', $this->requestPath(0));
        self::assertSame('application/json', $create->getHeaderLine('Content-Type'));
        self::assertSame([
            'startRow' => base64_encode('row1'),
            'endRow' => base64_encode('row9'),
        ], json_decode((string) $create->getBody(), true));

        $get = $this->history[1]['request'];
        self::assertSame('GET', $get->getMethod());
        self::assertSame('/users/scanner/scan42', $this->requestPath(1));
        self::assertSame('count=25', $get->getUri()->getQuery());

        $delete = $this->history[2]['request'];
        self::assertSame('DELETE', $delete->getMethod());
        self::assertSame('/users/scanner/scan42', $this->requestPath(2));
    }

    public function test_tables_list_parsed(): void
    {
        $client = $this->client([
            new Response(200, [], '{"table":[{"name":"users"},{"name":"orders"}]}'),
        ]);

        self::assertSame(['users', 'orders'], $client->tables());

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://hbase.local:8080/', (string) $request->getUri());
        self::assertSame('/', $this->requestPath(0));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(
            'Basic ' . base64_encode('alice:secret'),
            $request->getHeaderLine('Authorization'),
        );
    }

    public function test_create_table_sends_schema_body(): void
    {
        $client = $this->client([new Response(200)]);
        $client->createTable('users', ['info', 'data']);

        $request = $this->history[0]['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/users/schema', $this->requestPath(0));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'name' => 'users',
            'ColumnSchema' => [['name' => 'info'], ['name' => 'data']],
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_delete_table_sends_delete_to_schema(): void
    {
        $client = $this->client([new Response(200)]);
        $client->deleteTable('users');

        $request = $this->history[0]['request'];

        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/users/schema', $this->requestPath(0));
    }

    public function test_scan_creates_gets_and_deletes_scanner(): void
    {
        $client = $this->client([
            new Response(201, ['Location' => '/users/scanner/scan7']),
            new Response(200, [], self::ROW_RESPONSE),
            new Response(200),
        ]);

        self::assertSame(
            [['key' => 'row1', 'cells' => ['cf:a' => 'value']]],
            $client->scan('users', ['startRow' => 'row1', 'limit' => 2]),
        );

        self::assertSame(
            ['POST', 'GET', 'DELETE'],
            array_map(
                static fn (array $entry): string => $entry['request']->getMethod(),
                $this->history,
            ),
        );

        self::assertSame(
            ['startRow' => base64_encode('row1')],
            json_decode((string) $this->history[0]['request']->getBody(), true),
        );
        self::assertSame('count=2', $this->history[1]['request']->getUri()->getQuery());
    }

    public function test_exhausted_scanner_returns_empty_array(): void
    {
        $client = $this->client([new Response(204)]);

        self::assertSame([], $client->scannerGet('/users/scanner/scan42'));
    }

    public function test_non_2xx_response_throws_request_exception(): void
    {
        $client = $this->client([new Response(304, [], 'not modified')]);

        try {
            $client->tables();
            self::fail('Expected a RequestException for HTTP 304.');
        } catch (RequestException $e) {
            self::assertSame(304, $e->statusCode);
            self::assertSame('not modified', $e->body);
        }
    }

    public function test_logs_debug_request_without_credentials(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $client = $this->client([new Response(200)], $logger);
        $client->put('users', 'row1', ['cf:a' => 'value']);

        $debug = array_values(array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === LogLevel::DEBUG,
        ));

        self::assertNotEmpty($debug);
        self::assertSame('PUT', $debug[0]['context']['method'] ?? null);
        self::assertStringContainsString('/users/', $debug[0]['context']['uri'] ?? '');
        self::assertSame(['method', 'uri'], array_keys($debug[0]['context']));

        $logged = json_encode($logger->records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret', $logged);
        self::assertStringNotContainsString(base64_encode('value'), $logged);
    }
}
