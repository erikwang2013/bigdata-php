<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Oozie;

use Erikwang2013\BigData\Driver\Oozie\OozieClient;
use Erikwang2013\BigData\Driver\Oozie\OozieConfig;
use Erikwang2013\BigData\Exception\ConnectionException;
use Erikwang2013\BigData\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class OozieClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, array $config = []): OozieClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new OozieClient(
            OozieConfig::fromArray(array_merge(['baseUrl' => 'http://oozie.local:11000/oozie'], $config)),
            new Client(['handler' => $stack]),
        );
    }

    public function test_submit_workflow_posts_xml_and_returns_id(): void
    {
        $xml = '<configuration><property><name>oozie.wf.application.path</name>'
            . '<value>hdfs:///apps/demo/workflow.xml</value></property></configuration>';
        $client = $this->client([
            new Response(201, ['Content-Type' => 'application/json'], '{"id":"job-123"}'),
        ]);

        self::assertSame('job-123', $client->submitWorkflow($xml));

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://oozie.local:11000/oozie/v2/jobs', (string) $request->getUri());
        self::assertSame('application/xml', $request->getHeaderLine('Content-Type'));
        self::assertSame($xml, (string) $request->getBody());
    }

    public function test_submit_falls_back_to_plain_text_id(): void
    {
        $client = $this->client([new Response(201, ['Content-Type' => 'text/plain'], "0000001-261029120000000-oozie-oozi-W\n")]);

        self::assertSame('0000001-261029120000000-oozie-oozi-W', $client->submitWorkflow('<configuration></configuration>'));
    }

    public function test_submit_coordinator_and_bundle_post_xml(): void
    {
        $coordinator = '<configuration><property><name>oozie.coord.application.path</name>'
            . '<value>hdfs:///apps/demo/coordinator.xml</value></property></configuration>';
        $bundle = '<configuration><property><name>oozie.bundle.application.path</name>'
            . '<value>hdfs:///apps/demo/bundle.xml</value></property></configuration>';
        $client = $this->client([
            new Response(201, ['Content-Type' => 'application/json'], '{"id":"job-234"}'),
            new Response(201, ['Content-Type' => 'application/json'], '{"id":"job-345"}'),
        ]);

        self::assertSame('job-234', $client->submitCoordinator($coordinator));
        self::assertSame('job-345', $client->submitBundle($bundle));

        self::assertSame($coordinator, (string) $this->history[0]['request']->getBody());
        self::assertSame($bundle, (string) $this->history[1]['request']->getBody());
    }

    public function test_start_sends_action_query(): void
    {
        $client = $this->client([new Response(200)]);

        $client->start('0000001-261029120000000-oozie-oozi-W');

        $request = $this->history[0]['request'];
        self::assertSame('PUT', $request->getMethod());
        self::assertSame(
            'http://oozie.local:11000/oozie/v2/job/0000001-261029120000000-oozie-oozi-W?action=start',
            (string) $request->getUri(),
        );
    }

    public function test_all_actions_send_their_query(): void
    {
        $client = $this->client(array_fill(0, 3, new Response(200)));

        $client->suspend('job-1');
        $client->resume('job-1');
        $client->kill('job-1');

        $queries = array_map(
            static fn (array $entry): string => $entry['request']->getUri()->getQuery(),
            $this->history,
        );
        self::assertSame(['action=suspend', 'action=resume', 'action=kill'], $queries);
    }

    public function test_status_parsed(): void
    {
        $client = $this->client([
            new Response(200, ['Content-Type' => 'application/json'], '{"status":"SUCCEEDED"}'),
        ]);

        self::assertSame('SUCCEEDED', $client->status('job-1'));
        self::assertSame('http://oozie.local:11000/oozie/v2/job/job-1?show=status', (string) $this->history[0]['request']->getUri());
    }

    public function test_status_falls_back_to_plain_text(): void
    {
        $client = $this->client([new Response(200, ['Content-Type' => 'text/plain'], "RUNNING\n")]);

        self::assertSame('RUNNING', $client->status('job-1'));
    }

    public function test_log_returns_text(): void
    {
        $client = $this->client([new Response(200, ['Content-Type' => 'text/plain'], "line one\nline two")]);

        self::assertSame("line one\nline two", $client->log('job-1'));
        self::assertSame('show=log', $this->history[0]['request']->getUri()->getQuery());
    }

    public function test_job_returns_info_payload(): void
    {
        $info = ['id' => 'job-1', 'status' => 'RUNNING', 'appName' => 'demo'];
        $client = $this->client([new Response(200, [], json_encode($info))]);

        self::assertSame($info, $client->job('job-1'));
        self::assertSame('show=info', $this->history[0]['request']->getUri()->getQuery());
    }

    public function test_job_error_body_throws(): void
    {
        $client = $this->client([
            new Response(200, [], '{"error":"invalid_parameter","message":"Job does not exist: missing"}'),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Job does not exist: missing');
        $client->job('missing');
    }

    public function test_error_body_throws(): void
    {
        $body = '{"error":"invalid_parameter","message":"Job does not exist: missing"}';
        $client = $this->client([new Response(404, [], $body)]);

        try {
            $client->start('missing');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertSame($body, $e->body);
            self::assertStringContainsString('Job does not exist: missing', $e->getMessage());
        }
    }

    public function test_304_response_throws_request_exception(): void
    {
        $client = $this->client([new Response(304, [], 'not modified')]);

        try {
            $client->status('job-1');
            self::fail('Expected RequestException.');
        } catch (RequestException $e) {
            self::assertSame(304, $e->statusCode);
            self::assertSame('not modified', $e->body);
        }
    }

    public function test_rerun_sends_properties_xml(): void
    {
        $client = $this->client([new Response(200)]);

        $client->rerun('job-1', ['oozie.wf.rerun.failnodes' => 'true', 'custom' => 'A&B <x>']);

        $request = $this->history[0]['request'];
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('action=rerun', $request->getUri()->getQuery());
        self::assertSame('application/xml', $request->getHeaderLine('Content-Type'));

        $body = (string) $request->getBody();
        self::assertStringContainsString('<configuration>', $body);
        self::assertStringContainsString(
            '<property><name>oozie.wf.rerun.failnodes</name><value>true</value></property>',
            $body,
        );
        self::assertStringContainsString('<value>A&amp;B &lt;x&gt;</value>', $body);
    }

    public function test_basic_auth_is_sent_when_username_configured(): void
    {
        $client = $this->client([new Response(200)], ['username' => 'alice', 'password' => 'secret']);

        $client->status('job-1');

        self::assertSame(
            'alice:secret',
            base64_decode(substr($this->history[0]['request']->getHeaderLine('Authorization'), 6)),
        );
    }

    public function test_connection_failure_throws_connection_exception(): void
    {
        $mock = new MockHandler([new \GuzzleHttp\Exception\ConnectException(
            'Connection refused',
            new \GuzzleHttp\Psr7\Request('GET', 'http://oozie.local:11000/oozie/v2/job/job-1'),
        )]);
        $client = new OozieClient(
            OozieConfig::fromArray(['baseUrl' => 'http://oozie.local:11000/oozie']),
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->expectException(ConnectionException::class);
        $client->status('job-1');
    }

    public function test_base_url_alias_is_accepted(): void
    {
        self::assertSame(
            'http://oozie.alias:11000/oozie',
            OozieConfig::fromArray(['base_url' => 'http://oozie.alias:11000/oozie'])->baseUrl,
        );

        self::assertSame(
            'http://oozie.explicit:11000/oozie',
            OozieConfig::fromArray([
                'baseUrl' => 'http://oozie.explicit:11000/oozie',
                'base_url' => 'http://oozie.alias:11000/oozie',
            ])->baseUrl,
        );
    }

    public function test_verify_ssl_aliases_are_accepted(): void
    {
        self::assertFalse(OozieConfig::fromArray(['baseUrl' => 'http://oozie.local:11000/oozie', 'verify_ssl' => false])->verifySsl);
        self::assertFalse(OozieConfig::fromArray(['baseUrl' => 'http://oozie.local:11000/oozie', 'verifySsl' => false])->verifySsl);
    }
}
