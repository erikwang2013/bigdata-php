<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Trino;

use Erikwang2013\BigData\Driver\Trino\Exception\RequestException;
use Erikwang2013\BigData\Driver\Trino\ResultSet;
use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Driver\Trino\TrinoConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class TrinoClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, ?LoggerInterface $logger = null): TrinoClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new TrinoClient(
            TrinoConfig::fromArray(['host' => 'trino.local', 'username' => 'alice', 'password' => 'secret', 'catalog' => 'hive', 'schema' => 'default']),
            new Client(['handler' => $stack]),
            $logger,
        );
    }

    public function test_query_follows_next_uri_and_merges_rows(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'id' => 'q1',
                'nextUri' => 'http://trino.local:8080/v1/statement/q1/1',
                'columns' => [['name' => 'id', 'type' => 'integer']],
                'data' => [[1]],
                'stats' => ['state' => 'QUEUED'],
            ])),
            new Response(200, [], json_encode([
                'id' => 'q1',
                'columns' => [['name' => 'id', 'type' => 'integer']],
                'data' => [[2]],
                'stats' => ['state' => 'FINISHED'],
            ])),
        ]);

        $result = $client->query('SELECT id FROM t');

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['name' => 'id', 'type' => 'integer']], $result->columns());
        self::assertSame([[1], [2]], $result->rows());
        self::assertSame(2, $result->count());
    }

    public function test_query_throws_on_application_error_body(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'id' => 'q1',
                'error' => ['message' => "Column 'x' cannot be resolved", 'errorCode' => 47],
            ])),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Column 'x' cannot be resolved");
        $client->query('SELECT x');
    }

    public function test_basic_auth_and_trino_headers_are_sent(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['id' => 'q1', 'columns' => [], 'data' => [], 'stats' => ['state' => 'FINISHED']])),
        ]);

        $client->query('SELECT 1');
        $request = $this->history[0]['request'];

        self::assertSame('alice:secret', base64_decode(substr($request->getHeaderLine('Authorization'), 6)));
        self::assertSame('alice', $request->getHeaderLine('X-Trino-User'));
        self::assertSame('hive', $request->getHeaderLine('X-Trino-Catalog'));
        self::assertSame('default', $request->getHeaderLine('X-Trino-Schema'));
        self::assertSame('SELECT 1', (string) $request->getBody());
    }

    public function test_cancel_sends_delete_to_next_uri(): void
    {
        $client = $this->client([new Response(204)]);
        $client->cancel('http://trino.local:8080/v1/statement/q1/1');
        $request = $this->history[0]['request'];

        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('http://trino.local:8080/v1/statement/q1/1', (string) $request->getUri());
    }

    public function test_query_returns_null_values_unchanged(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'id' => 'q1', 'columns' => [['name' => 'v', 'type' => 'varchar']],
                'data' => [[null], ['']], 'stats' => ['state' => 'FINISHED'],
            ])),
        ]);

        self::assertSame([[null], ['']], $client->query('SELECT v')->rows());
    }

    public function test_query_logs_debug_request_to_injected_logger(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $client = $this->client([
            new Response(200, [], json_encode([
                'id' => 'q1', 'columns' => [['name' => 'id', 'type' => 'integer']],
                'data' => [[1]], 'stats' => ['state' => 'FINISHED'],
            ])),
        ], $logger);

        $client->query('SELECT 1');

        $debug = array_values(array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === LogLevel::DEBUG,
        ));

        self::assertNotEmpty($debug);
        self::assertSame('POST', $debug[0]['context']['method'] ?? null);
        self::assertStringContainsString('/v1/statement', $debug[0]['context']['uri'] ?? '');
    }

    public function test_non_2xx_status_is_treated_as_an_error(): void
    {
        // 304 is not followed by Guzzle, so it reaches the status check.
        $client = $this->client([new Response(304, [], '')]);

        try {
            $client->query('SELECT 1');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(304, $e->statusCode);
        }
    }
}
