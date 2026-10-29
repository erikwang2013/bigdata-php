<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Azkaban;

use Erikwang2013\BigData\Driver\Azkaban\AzkabanClient;
use Erikwang2013\BigData\Driver\Azkaban\AzkabanConfig;
use Erikwang2013\BigData\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AzkabanClientTest extends TestCase
{
    public function test_accepts_base_url_camel_case_alias(): void
    {
        $config = AzkabanConfig::fromArray([
            'baseUrl' => 'https://azkaban.local:8443',
            'username' => 'alice',
            'password' => 'secret',
        ]);

        self::assertSame('https://azkaban.local:8443', $config->baseUrl);
    }

    private array $history = [];

    private function client(array $responses): AzkabanClient
    {
        $this->history = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new AzkabanClient(
            AzkabanConfig::fromArray([
                'base_url' => 'https://azkaban.local:8443',
                'username' => 'alice',
                'password' => 'secret',
            ]),
            new Client(['handler' => $stack]),
        );
    }

    public function test_login_returns_and_caches_session_id(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['session.id' => 'sess-1', 'status' => 'success'])),
        ]);

        self::assertSame('sess-1', $client->login());
        self::assertSame('sess-1', $client->login());
        self::assertCount(1, $this->history);

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/manager', $request->getUri()->getPath());

        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame('login', $query['action']);

        parse_str((string) $request->getBody(), $form);
        self::assertSame('login', $form['action']);
        self::assertSame('alice', $form['username']);
        self::assertSame('secret', $form['password']);
    }

    public function test_session_id_sent_as_query_param(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['session.id' => 'sess-1'])),
            new Response(200, [], json_encode(['projects' => [['name' => 'demo']]])),
        ]);

        $result = $client->projects();

        self::assertSame(['projects' => [['name' => 'demo']]], $result);

        $uri = $this->history[1]['request']->getUri();
        self::assertSame('/index', $uri->getPath());
        self::assertSame('ajax=fetchuserprojects&session.id=sess-1', $uri->getQuery());
    }

    public function test_execute_flow_posts_project_and_flow(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['session.id' => 'sess-1'])),
            new Response(200, [], json_encode(['execid' => '42'])),
        ]);

        $result = $client->executeFlow('demo', 'etl', ['disabled' => 'a,b']);

        self::assertSame(['execid' => '42'], $result);

        $request = $this->history[1]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/executor', $request->getUri()->getPath());
        self::assertSame('ajax=executeFlow&session.id=sess-1', $request->getUri()->getQuery());

        parse_str((string) $request->getBody(), $form);
        self::assertSame('demo', $form['project']);
        self::assertSame('etl', $form['flow']);
        self::assertSame('a,b', $form['disabled']);
    }

    public function test_error_field_throws(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['session.id' => 'sess-1'])),
            new Response(200, [], json_encode(['error' => 'Project demo does not exist.'])),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Project demo does not exist.');
        $client->flows('demo');
    }

    public function test_execution_log_returns_text(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['session.id' => 'sess-1'])),
            new Response(200, [], json_encode(['length' => 5, 'offset' => 0, 'data' => 'hello'])),
        ]);

        self::assertSame('hello', $client->executionLog('42', 0, 5));

        $uri = $this->history[1]['request']->getUri();
        self::assertSame('/executor', $uri->getPath());
        self::assertSame(
            'ajax=fetchExecFlowLogs&execid=42&offset=0&length=5&session.id=sess-1',
            $uri->getQuery(),
        );
    }

    public function test_execution_log_without_data_throws(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['session.id' => 'sess-1'])),
            new Response(200, [], '{}'),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Azkaban execution log response does not contain "data".');
        $client->executionLog('42');
    }

    public function test_verify_ssl_aliases_are_accepted(): void
    {
        $config = ['base_url' => 'https://azkaban.local:8443', 'username' => 'alice', 'password' => 'secret'];

        self::assertFalse(AzkabanConfig::fromArray($config + ['verify_ssl' => false])->verifySsl);
        self::assertFalse(AzkabanConfig::fromArray($config + ['verifySsl' => false])->verifySsl);
    }

    public function test_304_response_throws_with_status_and_body(): void
    {
        $client = $this->client([
            new Response(304, [], 'Not Modified'),
        ]);

        try {
            $client->login();
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(304, $e->statusCode);
            self::assertSame('Not Modified', $e->body);
        }
    }
}
