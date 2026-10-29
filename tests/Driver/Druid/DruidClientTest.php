<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Druid;

use Erikwang2013\BigData\Driver\Druid\DruidClient;
use Erikwang2013\BigData\Driver\Druid\DruidConfig;
use Erikwang2013\BigData\Driver\Druid\ResultSet;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DruidClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, array $config = []): DruidClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new DruidClient(
            DruidConfig::fromArray($config + ['host' => 'druid.local']),
            new Client(['handler' => $stack]),
        );
    }

    private function sentBody(int $index = 0): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }

    public function test_sql_posts_query_and_parses_objects(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                ['__time' => '2026-01-01T00:00:00.000Z', 'count' => 3],
                ['__time' => '2026-01-01T00:01:00.000Z', 'count' => 7],
            ])),
        ]);

        $result = $client->sql('SELECT __time, COUNT(*) AS count FROM metrics GROUP BY 1', ['timeout' => 5000]);

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['__time', 'count'], $result->columns());
        self::assertSame([
            ['__time' => '2026-01-01T00:00:00.000Z', 'count' => 3],
            ['__time' => '2026-01-01T00:01:00.000Z', 'count' => 7],
        ], $result->rows());
        self::assertSame(2, $result->count());

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://druid.local:8888/druid/v2/sql', (string) $request->getUri());
        self::assertSame([
            'query' => 'SELECT __time, COUNT(*) AS count FROM metrics GROUP BY 1',
            'resultFormat' => 'object',
            'context' => ['timeout' => 5000],
        ], $this->sentBody());
    }

    public function test_sql_preserves_null_and_empty_string(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                ['v' => null],
                ['v' => ''],
                ['v' => 0],
            ])),
        ]);

        self::assertSame([['v' => null], ['v' => ''], ['v' => 0]], $client->sql('SELECT v')->rows());
    }

    public function test_sql_empty_context_is_sent_as_json_object(): void
    {
        $client = $this->client([new Response(200, [], '[]')]);

        $result = $client->sql('SELECT 1');

        self::assertSame([], $result->rows());
        self::assertSame([], $result->columns());
        self::assertStringContainsString('"context":{}', (string) $this->history[0]['request']->getBody());
    }

    public function test_native_query_posts_verbatim_body(): void
    {
        $query = [
            'queryType' => 'timeseries',
            'dataSource' => 'metrics',
            'granularity' => 'all',
            'aggregations' => [['type' => 'count', 'name' => 'rows']],
            'intervals' => ['2026-01-01/2026-02-01'],
        ];

        $client = $this->client([
            new Response(200, [], json_encode([
                ['timestamp' => '2026-01-01T00:00:00.000Z', 'result' => ['rows' => 5]],
            ])),
        ]);

        $result = $client->nativeQuery($query);

        self::assertSame([['timestamp' => '2026-01-01T00:00:00.000Z', 'result' => ['rows' => 5]]], $result);

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://druid.local:8888/druid/v2', (string) $request->getUri());
        self::assertSame($query, $this->sentBody());
    }

    public function test_datasources_returns_list(): void
    {
        $client = $this->client([new Response(200, [], json_encode(['wikipedia', 'metrics']))]);

        self::assertSame(['wikipedia', 'metrics'], $client->datasources());

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://druid.local:8888/druid/v2/datasources', (string) $request->getUri());
    }

    public function test_basic_auth_sent(): void
    {
        $client = $this->client(
            [new Response(200, [], '[]')],
            ['username' => 'druid', 'password' => 'secret'],
        );

        $client->sql('SELECT 1');

        self::assertSame(
            'Basic ' . base64_encode('druid:secret'),
            $this->history[0]['request']->getHeaderLine('Authorization'),
        );
    }

    public function test_bearer_token_takes_precedence_over_basic_auth(): void
    {
        $client = $this->client(
            [new Response(200, [], '[]')],
            ['username' => 'druid', 'password' => 'secret', 'token' => 'abc123'],
        );

        $client->sql('SELECT 1');

        self::assertSame('Bearer abc123', $this->history[0]['request']->getHeaderLine('Authorization'));
    }
}
