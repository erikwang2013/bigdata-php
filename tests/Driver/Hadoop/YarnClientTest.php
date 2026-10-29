<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hadoop;

use Erikwang2013\BigData\Driver\Hadoop\YarnClient;
use Erikwang2013\BigData\Driver\Hadoop\YarnConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class YarnClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses): YarnClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new YarnClient(
            YarnConfig::fromArray(['host' => 'rm.local', 'user' => 'php']),
            new Client(['handler' => $stack, 'allow_redirects' => false]),
        );
    }

    public function test_yarn_cluster_metrics_parsed(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'clusterMetrics' => ['appsSubmitted' => 42, 'appsRunning' => 3, 'totalMB' => 8192],
            ])),
        ]);

        self::assertSame(
            ['appsSubmitted' => 42, 'appsRunning' => 3, 'totalMB' => 8192],
            $client->clusterMetrics(),
        );

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/ws/v1/cluster/metrics', $request->getUri()->getPath());

        $query = Query::parse($request->getUri()->getQuery());
        self::assertSame('php', $query['user.name']);
    }

    public function test_kill_app_sends_killed_state(): void
    {
        $client = $this->client([new Response(200, [], json_encode(['state' => 'KILLED']))]);

        $client->killApp('application_1234_0001');

        $request = $this->history[0]['request'];
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/ws/v1/cluster/apps/application_1234_0001/state', $request->getUri()->getPath());
        self::assertSame('{"state":"KILLED"}', (string) $request->getBody());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }
}
