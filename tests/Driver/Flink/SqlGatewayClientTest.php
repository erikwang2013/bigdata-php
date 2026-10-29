<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Flink;

use Erikwang2013\BigData\Driver\Flink\FlinkConfig;
use Erikwang2013\BigData\Driver\Flink\SqlGatewayClient;
use Erikwang2013\BigData\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class SqlGatewayClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, ?LoggerInterface $logger = null): SqlGatewayClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SqlGatewayClient(
            FlinkConfig::fromArray(['baseUrl' => 'http://flink.local:8083', 'token' => 'secret']),
            new Client(['handler' => $stack]),
            $logger,
        );
    }

    public function test_open_session_returns_handle(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['sessionHandle' => 'session-1'])),
        ]);

        self::assertSame('session-1', $client->openSession(['execution.target' => 'local']));

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://flink.local:8083/v1/sessions', (string) $request->getUri());
        self::assertSame('{"properties":{"execution.target":"local"}}', (string) $request->getBody());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('Bearer secret', $request->getHeaderLine('Authorization'));
    }

    public function test_open_session_encodes_empty_properties_as_object(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['sessionHandle' => 'session-1'])),
        ]);

        self::assertSame('session-1', $client->openSession());

        self::assertSame('{"properties":{}}', (string) $this->history[0]['request']->getBody());
    }

    public function test_execute_statement_returns_operation_handle(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['operationHandle' => 'operation-1'])),
        ]);

        self::assertSame('operation-1', $client->executeStatement('session-1', 'SELECT 1'));

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/sessions/session-1/statements', $request->getUri()->getPath());
        self::assertSame('{"statement":"SELECT 1"}', (string) $request->getBody());
    }

    public function test_fetch_results_maps_columns_and_rows(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'results' => [
                    'columns' => [['name' => 'id', 'logicalType' => ['type' => 'INT']]],
                    'data' => [['kind' => 'INSERT', 'fields' => [1]]],
                    'resultType' => 'EOS',
                ],
            ])),
        ]);

        $result = $client->fetchResults('session-1', 'operation-1');

        self::assertSame([['name' => 'id', 'logicalType' => ['type' => 'INT']]], $result['columns']);
        self::assertSame([[1]], $result['rows']);
        self::assertTrue($result['end']);
        self::assertNull($result['nextToken']);
        self::assertSame(
            '/v1/sessions/session-1/operations/operation-1/result/0',
            $this->history[0]['request']->getUri()->getPath(),
        );
    }

    public function test_fetch_results_reports_next_token_until_eos(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'results' => ['columns' => [], 'data' => [], 'resultType' => 'NOT_READY'],
            ])),
            new Response(200, [], json_encode([
                'results' => ['columns' => [], 'data' => [], 'resultType' => 'EOS'],
            ])),
        ]);

        $first = $client->fetchResults('session-1', 'operation-1', 0);
        self::assertFalse($first['end']);
        self::assertSame(1, $first['nextToken']);

        $second = $client->fetchResults('session-1', 'operation-1', $first['nextToken']);
        self::assertTrue($second['end']);
        self::assertNull($second['nextToken']);
        self::assertSame(
            '/v1/sessions/session-1/operations/operation-1/result/1',
            $this->history[1]['request']->getUri()->getPath(),
        );
    }

    public function test_cancel_operation_uses_delete(): void
    {
        $client = $this->client([new Response(200, [], json_encode([]))]);

        $client->cancelOperation('session-1', 'operation-1');

        $request = $this->history[0]['request'];
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/sessions/session-1/operations/operation-1', $request->getUri()->getPath());
    }

    public function test_close_session_uses_delete(): void
    {
        $client = $this->client([new Response(200, [], json_encode([]))]);

        $client->closeSession('session-1');

        $request = $this->history[0]['request'];
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/sessions/session-1', $request->getUri()->getPath());
    }

    public function test_non_2xx_response_throws_request_exception(): void
    {
        $client = $this->client([new Response(304, [], '{"sessionHandle":"session-1"}')]);

        try {
            $client->openSession();
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(304, $e->statusCode);
            self::assertSame('{"sessionHandle":"session-1"}', $e->body);
        }
    }

    public function test_http_error_throws_request_exception(): void
    {
        $client = $this->client([new Response(400, [], '{"errors":["bad statement"]}')]);

        try {
            $client->executeStatement('session-1', 'SELECT');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertSame('{"errors":["bad statement"]}', $e->body);
        }
    }

    public function test_debug_log_records_method_and_uri_without_sql(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $client = $this->client([
            new Response(200, [], json_encode(['operationHandle' => 'operation-1'])),
        ], $logger);

        $client->executeStatement('session-1', 'SELECT secret FROM t');

        $debug = array_values(array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === LogLevel::DEBUG,
        ));

        self::assertNotEmpty($debug);
        self::assertSame('POST', $debug[0]['context']['method'] ?? null);
        self::assertStringContainsString('/v1/sessions/session-1/statements', $debug[0]['context']['uri'] ?? '');
        self::assertStringNotContainsString('SELECT', json_encode($debug[0]['context']));
    }

    public function test_config_requires_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlinkConfig::fromArray([]);
    }
}
