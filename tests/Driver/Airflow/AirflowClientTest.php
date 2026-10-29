<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Airflow;

use Erikwang2013\BigData\Driver\Airflow\AirflowClient;
use Erikwang2013\BigData\Driver\Airflow\AirflowConfig;
use Erikwang2013\BigData\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AirflowClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, array $config = []): AirflowClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new AirflowClient(
            AirflowConfig::fromArray(array_merge(['baseUrl' => 'http://airflow.local:8080'], $config)),
            new Client(['handler' => $stack]),
        );
    }

    public function test_dags_sends_limit_and_offset(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['dags' => [['dag_id' => 'etl']], 'total_entries' => 1])),
        ]);

        $result = $client->dags(5, 10);
        $request = $this->history[0]['request'];

        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://airflow.local:8080/api/v1/dags?limit=5&offset=10', (string) $request->getUri());
        self::assertSame([['dag_id' => 'etl']], $result['dags']);
    }

    public function test_trigger_dag_posts_conf_and_run_id(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['dag_run_id' => 'run_1', 'state' => 'queued'])),
            new Response(200, [], json_encode(['dag_run_id' => 'manual__2026-10-29', 'state' => 'queued'])),
        ]);

        $run = $client->triggerDag('etl', ['key' => 'value'], 'run_1');
        $request = $this->history[0]['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://airflow.local:8080/api/v1/dags/etl/dagRuns', (string) $request->getUri());
        self::assertSame(
            ['conf' => ['key' => 'value'], 'dag_run_id' => 'run_1'],
            json_decode((string) $request->getBody(), true),
        );
        self::assertSame('run_1', $run['dag_run_id']);

        $client->triggerDag('etl');
        self::assertSame(
            ['conf' => []],
            json_decode((string) $this->history[1]['request']->getBody(), true),
        );
    }

    public function test_task_log_returns_text(): void
    {
        $client = $this->client([
            new Response(200, [], "first line\nsecond line\n"),
        ]);

        $log = $client->taskLog('etl', 'run_1', 'extract', 2);
        $request = $this->history[0]['request'];

        self::assertSame("first line\nsecond line\n", $log);
        self::assertSame('text/plain', $request->getHeaderLine('Accept'));
        self::assertSame(
            'http://airflow.local:8080/api/v1/dags/etl/dagRuns/run_1/taskInstances/extract/logs/2',
            (string) $request->getUri(),
        );
    }

    public function test_basic_auth_and_bearer_token_paths(): void
    {
        $basic = $this->client(
            [new Response(200, [], json_encode(['dags' => []]))],
            ['username' => 'alice', 'password' => 'secret'],
        );
        $basic->dags();

        $bearer = $this->client(
            [new Response(200, [], json_encode(['dags' => []]))],
            ['token' => 'abc123'],
        );
        $bearer->dags();

        self::assertSame(
            'Basic ' . base64_encode('alice:secret'),
            $this->history[0]['request']->getHeaderLine('Authorization'),
        );
        self::assertSame(
            'Bearer abc123',
            $this->history[1]['request']->getHeaderLine('Authorization'),
        );
    }

    public function test_404_is_mapped(): void
    {
        $client = $this->client([
            new Response(404, [], json_encode(['detail' => 'DAG not found'])),
        ]);

        try {
            $client->dag('missing');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertStringContainsString('DAG not found', $e->body);
        }
    }

    public function test_api_path_is_configurable(): void
    {
        $client = $this->client(
            [new Response(200, [], json_encode(['dags' => []]))],
            ['api_path' => '/api/v2'],
        );

        $client->dags();

        self::assertSame(
            'http://airflow.local:8080/api/v2/dags?limit=100&offset=0',
            (string) $this->history[0]['request']->getUri(),
        );
    }

    public function test_base_url_alias_is_accepted(): void
    {
        self::assertSame(
            'http://airflow.alias:8080',
            AirflowConfig::fromArray(['base_url' => 'http://airflow.alias:8080'])->baseUrl,
        );

        self::assertSame(
            'http://airflow.explicit:8080',
            AirflowConfig::fromArray([
                'baseUrl' => 'http://airflow.explicit:8080',
                'base_url' => 'http://airflow.alias:8080',
            ])->baseUrl,
        );
    }

    public function test_verify_ssl_aliases_are_accepted(): void
    {
        self::assertFalse(AirflowConfig::fromArray(['baseUrl' => 'http://airflow.local:8080', 'verify_ssl' => false])->verifySsl);
        self::assertFalse(AirflowConfig::fromArray(['baseUrl' => 'http://airflow.local:8080', 'verifySsl' => false])->verifySsl);
    }
}
