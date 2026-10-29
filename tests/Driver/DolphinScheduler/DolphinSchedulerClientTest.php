<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\DolphinScheduler;

use Erikwang2013\BigData\Driver\DolphinScheduler\DolphinSchedulerClient;
use Erikwang2013\BigData\Driver\DolphinScheduler\DolphinSchedulerConfig;
use Erikwang2013\BigData\Driver\DolphinScheduler\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\DolphinScheduler\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

final class DolphinSchedulerClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, array $config = [], ?AbstractLogger $logger = null): DolphinSchedulerClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new DolphinSchedulerClient(
            DolphinSchedulerConfig::fromArray($config + [
                'baseUrl' => 'http://ds.local:12345/dolphinscheduler',
                'username' => 'admin',
                'password' => 'secret',
            ]),
            new Client(['handler' => $stack]),
            $logger,
        );
    }

    private function loginResponse(string $sessionId = 'session-1'): Response
    {
        return new Response(200, [], json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => ['sessionId' => $sessionId],
        ]));
    }

    private function jsonResponse(mixed $data): Response
    {
        return new Response(200, [], json_encode(['code' => 0, 'msg' => 'success', 'data' => $data]));
    }

    private function query(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);

        return $query;
    }

    public function test_login_caches_session_id(): void
    {
        $client = $this->client([$this->loginResponse('session-abc')]);

        self::assertSame('session-abc', $client->login());
        self::assertSame('session-abc', $client->login());

        self::assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/dolphinscheduler/login', $request->getUri()->getPath());

        parse_str((string) $request->getBody(), $form);
        self::assertSame('admin', $form['userName'] ?? null);
        self::assertSame('secret', $form['userPassword'] ?? null);
    }

    public function test_subsequent_requests_send_session_id_header(): void
    {
        $client = $this->client([
            $this->loginResponse('session-abc'),
            $this->jsonResponse(['totalList' => []]),
        ]);

        $client->projects();

        self::assertCount(2, $this->history);
        self::assertFalse($this->history[0]['request']->hasHeader('sessionId'));
        self::assertSame('session-abc', $this->history[1]['request']->getHeaderLine('sessionId'));
    }

    public function test_start_process_sends_definition_code(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            $this->jsonResponse(['processInstanceId' => 42]),
        ]);

        $result = $client->startProcess(7, 9001, ['failureStrategy' => 'CONTINUE']);

        self::assertSame(['processInstanceId' => 42], $result);

        $request = $this->history[1]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/dolphinscheduler/projects/7/executions', $request->getUri()->getPath());

        $body = json_decode((string) $request->getBody(), true);
        self::assertSame(9001, $body['processDefinitionCode']);
        self::assertSame('CONTINUE', $body['failureStrategy']);
    }

    public function test_code_non_zero_throws(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            new Response(200, [], json_encode(['code' => 10001, 'msg' => 'user not exist'])),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('user not exist');

        $client->projects();
    }

    public function test_connection_failure_throws_connection_exception(): void
    {
        $client = $this->client([
            new ConnectException(
                'Connection refused',
                new Request('POST', 'http://ds.local:12345/dolphinscheduler/login'),
            ),
        ]);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection refused');

        $client->login();
    }

    public function test_task_log_returns_plain_text(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            new Response(200, [], "log line 1\nlog line 2"),
        ]);

        $log = $client->taskLog(77, 10, 50);

        self::assertSame("log line 1\nlog line 2", $log);

        $request = $this->history[1]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/dolphinscheduler/log/77', $request->getUri()->getPath());

        $query = $this->query($request);
        self::assertSame('10', $query['skipLineNum'] ?? null);
        self::assertSame('50', $query['limit'] ?? null);
    }

    public function test_pagination_params_sent(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            $this->jsonResponse(['totalList' => [], 'total' => 0]),
        ]);

        $client->projects(2, 5);

        $request = $this->history[1]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/dolphinscheduler/projects', $request->getUri()->getPath());

        $query = $this->query($request);
        self::assertSame('2', $query['pageNo'] ?? null);
        self::assertSame('5', $query['pageSize'] ?? null);
    }

    public function test_process_definitions_are_project_scoped(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            $this->jsonResponse(['totalList' => []]),
        ]);

        $client->processDefinitions(7, 3, 20);

        $request = $this->history[1]['request'];
        self::assertSame('/dolphinscheduler/projects/7/process-definition', $request->getUri()->getPath());

        $query = $this->query($request);
        self::assertSame('3', $query['pageNo'] ?? null);
        self::assertSame('20', $query['pageSize'] ?? null);
    }

    public function test_stop_process_posts_to_stop_endpoint(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            $this->jsonResponse(null),
        ]);

        $client->stopProcess(7, 55);

        $request = $this->history[1]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/dolphinscheduler/projects/7/executions/55/stop', $request->getUri()->getPath());
    }

    public function test_process_instances_merges_filters(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            $this->jsonResponse(['totalList' => []]),
        ]);

        $client->processInstances(7, 1, 10, ['stateType' => 'FAILURE', 'processDefinitionCode' => 9001]);

        $query = $this->query($this->history[1]['request']);
        self::assertSame('FAILURE', $query['stateType'] ?? null);
        self::assertSame('9001', $query['processDefinitionCode'] ?? null);
        self::assertSame('1', $query['pageNo'] ?? null);
        self::assertSame('10', $query['pageSize'] ?? null);
    }

    public function test_task_instances_scoped_to_process_instance(): void
    {
        $client = $this->client([
            $this->loginResponse(),
            $this->jsonResponse([]),
        ]);

        $client->taskInstances(7, 55, 2, 25);

        $request = $this->history[1]['request'];
        self::assertSame('/dolphinscheduler/projects/7/task-instances', $request->getUri()->getPath());

        $query = $this->query($request);
        self::assertSame('55', $query['processInstanceId'] ?? null);
        self::assertSame('2', $query['pageNo'] ?? null);
        self::assertSame('25', $query['pageSize'] ?? null);
    }

    public function test_token_auth_skips_login(): void
    {
        $client = $this->client(
            [$this->jsonResponse(['totalList' => []])],
            ['token' => 'tok-123'],
        );

        $client->projects();

        self::assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        self::assertSame('tok-123', $request->getHeaderLine('token'));
        self::assertFalse($request->hasHeader('sessionId'));
    }

    public function test_login_does_not_log_credentials(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $this->client([$this->loginResponse()], [], $logger)->login();

        $logs = json_encode($logger->records);
        self::assertSame(LogLevel::DEBUG, $logger->records[0]['level'] ?? null);
        self::assertStringContainsString('/login', $logs);
        self::assertStringNotContainsString('secret', $logs);
    }

    public function test_base_url_alias_is_accepted(): void
    {
        $credentials = ['username' => 'admin', 'password' => 'secret'];

        self::assertSame(
            'http://ds.alias:12345/dolphinscheduler',
            DolphinSchedulerConfig::fromArray($credentials + ['base_url' => 'http://ds.alias:12345/dolphinscheduler/'])->baseUrl,
        );

        self::assertSame(
            'http://ds.explicit:12345/dolphinscheduler',
            DolphinSchedulerConfig::fromArray($credentials + [
                'baseUrl' => 'http://ds.explicit:12345/dolphinscheduler',
                'base_url' => 'http://ds.alias:12345/dolphinscheduler',
            ])->baseUrl,
        );
    }
}
