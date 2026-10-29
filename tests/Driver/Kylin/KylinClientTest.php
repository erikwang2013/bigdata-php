<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Kylin;

use Erikwang2013\BigData\Driver\Kylin\KylinClient;
use Erikwang2013\BigData\Driver\Kylin\KylinConfig;
use Erikwang2013\BigData\Exception\RequestException;
use Erikwang2013\BigData\Support\ResultSet;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class KylinClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, ?LoggerInterface $logger = null): KylinClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new KylinClient(
            KylinConfig::fromArray([
                'host' => 'kylin.local',
                'username' => 'admin',
                'password' => 'KYLIN',
                'project' => 'learn_kylin',
            ]),
            new Client(['handler' => $stack]),
            $logger,
        );
    }

    private function fixture(): array
    {
        return [
            'columnMetas' => [['name' => 'ID', 'label' => 'ID', 'dataType' => 'integer']],
            'results' => [[1]],
            'isException' => false,
        ];
    }

    public function test_query_maps_column_metas_and_results(): void
    {
        $payload = $this->fixture();
        $client = $this->client([new Response(200, [], json_encode($payload))]);

        $result = $client->query('SELECT ID FROM KYLIN_SALES');

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame($payload['columnMetas'], $result->columns());
        self::assertSame([[1]], $result->rows());
        self::assertSame(1, $result->count());
        self::assertSame([$payload], $result->raw());

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://kylin.local:7070/kylin/api/query', (string) $request->getUri());
    }

    public function test_query_throws_when_is_exception_true(): void
    {
        $client = $this->client([new Response(200, [], json_encode([
            'columnMetas' => [],
            'results' => [],
            'isException' => true,
            'exceptionMessage' => 'Table not found: UNKNOWN',
        ]))]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Table not found: UNKNOWN');
        $client->query('SELECT * FROM UNKNOWN');
    }

    public function test_query_sends_project_and_limit(): void
    {
        $client = $this->client([new Response(200, [], json_encode($this->fixture()))]);

        $client->query('SELECT ID FROM KYLIN_SALES', 'sales_project', 50, 20);

        $request = $this->history[0]['request'];
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'sql' => 'SELECT ID FROM KYLIN_SALES',
            'project' => 'sales_project',
            'limit' => 50,
            'offset' => 20,
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_query_falls_back_to_configured_project(): void
    {
        $client = $this->client([new Response(200, [], json_encode($this->fixture()))]);

        $client->query('SELECT 1');

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        self::assertSame('learn_kylin', $body['project']);
    }

    public function test_build_cube_sends_range_and_type(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['uuid' => 'job-1', 'status' => 'PENDING'])),
        ]);

        $result = $client->buildCube(
            'kylin_sales_cube',
            '2026-01-01 00:00:00',
            '2026-01-02 00:00:00',
            'REFRESH',
        );

        self::assertSame('job-1', $result['uuid']);

        $request = $this->history[0]['request'];
        self::assertSame('PUT', $request->getMethod());
        self::assertSame(
            'http://kylin.local:7070/kylin/api/cubes/kylin_sales_cube/rebuild',
            (string) $request->getUri(),
        );
        self::assertSame([
            'startTime' => '2026-01-01 00:00:00',
            'endTime' => '2026-01-02 00:00:00',
            'buildType' => 'REFRESH',
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_basic_auth_header_sent(): void
    {
        $client = $this->client([new Response(200, [], json_encode($this->fixture()))]);

        $client->query('SELECT 1');

        $authorization = $this->history[0]['request']->getHeaderLine('Authorization');
        self::assertStringStartsWith('Basic ', $authorization);
        self::assertSame('admin:KYLIN', base64_decode(substr($authorization, 6)));
    }

    public function test_metadata_endpoints_use_expected_paths_and_flatten_lists(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['totalSize' => 1, 'cubes' => [['name' => 'cube_a']]])),
            new Response(200, [], json_encode(['totalSize' => 1, 'models' => [['name' => 'model_a']]])),
            new Response(200, [], json_encode(['totalSize' => 1, 'jobs' => [['uuid' => 'job_a']]])),
        ]);

        self::assertSame([['name' => 'cube_a']], $client->cubes(10, 5));
        self::assertSame([['name' => 'model_a']], $client->models(10, 5));
        self::assertSame([['uuid' => 'job_a']], $client->jobs(['status' => 'RUNNING']));

        $cubesUri = $this->history[0]['request']->getUri();
        self::assertSame('/kylin/api/cubes', $cubesUri->getPath());
        parse_str($cubesUri->getQuery(), $cubesQuery);
        self::assertSame('learn_kylin', $cubesQuery['project']);
        self::assertSame('10', $cubesQuery['offset']);
        self::assertSame('5', $cubesQuery['limit']);

        $modelsUri = $this->history[1]['request']->getUri();
        self::assertSame('/kylin/api/models', $modelsUri->getPath());

        $jobsUri = $this->history[2]['request']->getUri();
        self::assertSame('/kylin/api/jobs', $jobsUri->getPath());
        parse_str($jobsUri->getQuery(), $jobsQuery);
        self::assertSame('RUNNING', $jobsQuery['status']);
        self::assertSame('learn_kylin', $jobsQuery['project']);
    }

    public function test_http_error_throws_request_exception_with_status_and_body(): void
    {
        $client = $this->client([new Response(500, [], 'boom')]);

        try {
            $client->query('SELECT 1');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(500, $e->statusCode);
            self::assertSame('boom', $e->body);
        }
    }

    public function test_config_requires_host_username_and_password(): void
    {
        foreach (['host', 'username', 'password'] as $missing) {
            $config = ['host' => 'kylin.local', 'username' => 'admin', 'password' => 'KYLIN'];
            unset($config[$missing]);

            try {
                KylinConfig::fromArray($config);
                self::fail(sprintf('Expected InvalidArgumentException for missing "%s".', $missing));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($missing, $e->getMessage());
            }
        }
    }

    public function test_verify_ssl_aliases_are_accepted(): void
    {
        $credentials = ['host' => 'kylin.local', 'username' => 'admin', 'password' => 'KYLIN'];

        self::assertFalse(KylinConfig::fromArray($credentials + ['verify_ssl' => false])->verifySsl);
        self::assertFalse(KylinConfig::fromArray($credentials + ['verifySsl' => false])->verifySsl);
    }

    public function test_query_logs_method_and_uri_but_never_sql_or_credentials(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $client = $this->client(
            [new Response(200, [], json_encode($this->fixture()))],
            $logger,
        );

        $client->query('SELECT SECRET_COLUMN FROM t');

        $debug = array_values(array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === LogLevel::DEBUG,
        ));

        self::assertNotEmpty($debug);
        self::assertSame('POST', $debug[0]['context']['method'] ?? null);
        self::assertStringContainsString('/kylin/api/query', $debug[0]['context']['uri'] ?? '');

        $logged = json_encode($logger->records);
        self::assertStringNotContainsString('SECRET_COLUMN', $logged);
        self::assertStringNotContainsString('admin:KYLIN', $logged);
    }
}
