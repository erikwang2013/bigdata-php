<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Spark;

use Erikwang2013\BigData\Driver\Spark\Exception\RequestException;
use Erikwang2013\BigData\Driver\Spark\LivyClient;
use Erikwang2013\BigData\Driver\Spark\LivyConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class LivyClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, ?LoggerInterface $logger = null): LivyClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new LivyClient(
            LivyConfig::fromArray(['host' => 'livy.local', 'username' => 'alice', 'password' => 's3cr3t-pw']),
            new Client(['handler' => $stack]),
            $logger,
        );
    }

    public function test_create_session_sends_kind_and_conf(): void
    {
        $client = $this->client([
            new Response(201, [], json_encode(['id' => 0, 'kind' => 'sql', 'state' => 'starting'])),
        ]);

        $session = $client->createSession('sql', ['spark.executor.memory' => '1g']);

        self::assertSame(0, $session['id']);

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://livy.local:8998/sessions', (string) $request->getUri());
        self::assertSame(
            ['kind' => 'sql', 'conf' => ['spark.executor.memory' => '1g']],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function test_run_statement_sends_code(): void
    {
        $client = $this->client([
            new Response(201, [], json_encode(['id' => 1, 'state' => 'waiting'])),
        ]);

        $statement = $client->runStatement(3, 'SELECT 1');

        self::assertSame(1, $statement['id']);

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://livy.local:8998/sessions/3/statements', (string) $request->getUri());
        self::assertSame(['code' => 'SELECT 1'], json_decode((string) $request->getBody(), true));
    }

    public function test_statement_returns_json(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'id' => 7,
                'state' => 'available',
                'output' => ['status' => 'ok', 'data' => ['application/json' => ['value' => 1]]],
            ])),
        ]);

        $statement = $client->statement(3, 7);

        self::assertSame('available', $statement['state']);
        self::assertSame(1, $statement['output']['data']['application/json']['value']);

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://livy.local:8998/sessions/3/statements/7', (string) $request->getUri());
    }

    public function test_create_batch_posts_body(): void
    {
        $batch = [
            'file' => 'hdfs:///jobs/wordcount.jar',
            'className' => 'org.example.Main',
            'args' => ['hdfs:///input'],
        ];

        $client = $this->client([
            new Response(201, [], json_encode(['id' => 5, 'state' => 'starting'])),
        ]);

        $created = $client->createBatch($batch);

        self::assertSame(5, $created['id']);

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://livy.local:8998/batches', (string) $request->getUri());
        self::assertSame($batch, json_decode((string) $request->getBody(), true));
    }

    public function test_every_request_has_x_requested_by_header(): void
    {
        $client = $this->client([
            new Response(201, [], '{"id":0}'),
            new Response(200, [], '{"id":0}'),
            new Response(201, [], '{"id":1}'),
            new Response(200, [], '{"id":1}'),
            new Response(200, [], '{"from":5,"total":0,"sessions":[]}'),
            new Response(201, [], '{"id":2}'),
            new Response(200, [], '{"id":2}'),
            new Response(200),
            new Response(200),
        ]);

        $client->createSession();
        $client->session(0);
        $client->runStatement(0, 'SELECT 1');
        $client->statement(0, 1);
        $client->batches(5, 10);
        $client->createBatch(['file' => 'hdfs:///jobs/x.jar']);
        $client->batch(2);
        $client->deleteSession(0);
        $client->deleteBatch(2);

        self::assertCount(9, $this->history);
        // An empty conf must still be a JSON object, not an array.
        self::assertSame('{"kind":"sql","conf":{}}', (string) $this->history[0]['request']->getBody());

        foreach ($this->history as $entry) {
            self::assertSame('php', $entry['request']->getHeaderLine('X-Requested-By'));
            self::assertSame('application/json', $entry['request']->getHeaderLine('Content-Type'));
        }

        self::assertSame('http://livy.local:8998/batches?from=5&size=10', (string) $this->history[4]['request']->getUri());
        self::assertSame('DELETE', $this->history[7]['request']->getMethod());
        self::assertSame('DELETE', $this->history[8]['request']->getMethod());
    }

    public function test_error_status_is_mapped(): void
    {
        $client = $this->client([
            new Response(404, [], '{"msg":"Session not found"}'),
        ]);

        try {
            $client->session(99);
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertSame('{"msg":"Session not found"}', $e->body);
            self::assertStringContainsString('404', $e->getMessage());
            self::assertStringContainsString('Session not found', $e->getMessage());
        }
    }

    public function test_non_2xx_status_is_mapped(): void
    {
        $client = $this->client([
            new Response(304, [], '{"msg":"Not modified"}'),
        ]);

        try {
            $client->session(1);
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(304, $e->statusCode);
            self::assertSame('{"msg":"Not modified"}', $e->body);
            self::assertStringContainsString('304', $e->getMessage());
        }
    }

    public function test_debug_logging_omits_code_and_credentials(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $client = $this->client([
            new Response(201, [], '{"id":1}'),
        ], $logger);

        $client->runStatement(3, 'SELECT secret FROM t');

        self::assertNotEmpty($logger->records);
        self::assertSame('POST', $logger->records[0]['context']['method'] ?? null);
        self::assertStringContainsString('/sessions/3/statements', $logger->records[0]['context']['uri'] ?? '');

        $logged = json_encode($logger->records);
        self::assertStringNotContainsString('SELECT secret', $logged);
        self::assertStringNotContainsString('alice', $logged);
        self::assertStringNotContainsString('s3cr3t-pw', $logged);
    }
}
