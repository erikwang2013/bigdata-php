<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Flink;

use Erikwang2013\BigData\Driver\Flink\FlinkConfig;
use Erikwang2013\BigData\Driver\Flink\JobClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class JobClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses): JobClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new JobClient(
            FlinkConfig::fromArray(['baseUrl' => 'http://flink.local:8081']),
            new Client(['handler' => $stack]),
        );
    }

    public function test_jobs_list_parsed(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'jobs' => [
                    ['jid' => 'job-1', 'name' => 'insert into sink', 'state' => 'RUNNING'],
                    ['jid' => 'job-2', 'name' => 'select source', 'state' => 'FINISHED'],
                ],
            ])),
        ]);

        $jobs = $client->jobs();

        self::assertCount(2, $jobs);
        self::assertSame('job-1', $jobs[0]['jid']);
        self::assertSame('RUNNING', $jobs[0]['state']);
        self::assertSame('job-2', $jobs[1]['jid']);

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://flink.local:8081/jobs', (string) $request->getUri());
    }

    public function test_job_returns_details(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'jid' => 'job-1',
                'name' => 'insert into sink',
                'state' => 'RUNNING',
                'vertices' => [],
            ])),
        ]);

        $job = $client->job('job-1');

        self::assertSame('job-1', $job['jid']);
        self::assertSame('RUNNING', $job['state']);
        self::assertSame('/jobs/job-1', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_cancel_uses_patch_with_cancel_mode(): void
    {
        $client = $this->client([new Response(200, [], json_encode([]))]);

        $client->cancel('job-1');

        $request = $this->history[0]['request'];
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame('/jobs/job-1', $request->getUri()->getPath());
        self::assertSame('mode=cancel', $request->getUri()->getQuery());
    }

    public function test_base_url_alias_is_accepted(): void
    {
        self::assertSame(
            'http://flink.alias:8081',
            FlinkConfig::fromArray(['base_url' => 'http://flink.alias:8081/'])->baseUrl,
        );

        self::assertSame(
            'http://flink.explicit:8081',
            FlinkConfig::fromArray([
                'baseUrl' => 'http://flink.explicit:8081',
                'base_url' => 'http://flink.alias:8081',
            ])->baseUrl,
        );
    }

    public function test_verify_ssl_aliases_are_accepted(): void
    {
        self::assertFalse(FlinkConfig::fromArray(['baseUrl' => 'http://flink.local:8081', 'verify_ssl' => false])->verifySsl);
        self::assertFalse(FlinkConfig::fromArray(['baseUrl' => 'http://flink.local:8081', 'verifySsl' => false])->verifySsl);
    }
}
