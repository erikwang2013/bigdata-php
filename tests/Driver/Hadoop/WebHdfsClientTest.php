<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hadoop;

use Erikwang2013\BigData\Driver\Hadoop\Exception\RequestException;
use Erikwang2013\BigData\Driver\Hadoop\WebHdfsClient;
use Erikwang2013\BigData\Driver\Hadoop\WebHdfsConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class WebHdfsClientTest extends TestCase
{
    private array $history = [];

    private function client(
        array $responses,
        array $clientConfig = [],
        ?LoggerInterface $logger = null,
        array $config = [],
    ): WebHdfsClient {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new WebHdfsClient(
            WebHdfsConfig::fromArray($config + ['host' => 'namenode.local', 'user' => 'php']),
            new Client($clientConfig + ['handler' => $stack]),
            $logger,
        );
    }

    public function test_list_status_sends_op_and_user_name(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'FileStatuses' => ['FileStatus' => [
                    ['pathSuffix' => 'a.csv', 'type' => 'FILE'],
                    ['pathSuffix' => 'b', 'type' => 'DIRECTORY'],
                ]],
            ])),
        ]);

        $statuses = $client->listStatus('/data');

        self::assertSame([
            ['pathSuffix' => 'a.csv', 'type' => 'FILE'],
            ['pathSuffix' => 'b', 'type' => 'DIRECTORY'],
        ], $statuses);

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'http://namenode.local:9870/webhdfs/v1/data?op=LISTSTATUS&user.name=php',
            (string) $request->getUri(),
        );
    }

    public function test_read_follows_307_without_forwarding_auth(): void
    {
        $client = $this->client(
            [
                new Response(307, ['Location' => 'http://datanode:9864/webhdfs/v1/data/file.txt?op=OPEN'], ''),
                new Response(200, [], 'hello'),
            ],
            ['headers' => ['Authorization' => 'Basic ' . base64_encode('php:secret')]],
        );

        self::assertSame('hello', $client->read('/data/file.txt'));
        self::assertCount(2, $this->history);

        $first = $this->history[0]['request'];
        $second = $this->history[1]['request'];

        self::assertSame('Basic ' . base64_encode('php:secret'), $first->getHeaderLine('Authorization'));
        self::assertFalse($second->hasHeader('Authorization'));

        $query = Query::parse($second->getUri()->getQuery());
        self::assertSame('php', $query['user.name']);
        self::assertSame('OPEN', $query['op']);
    }

    public function test_redirect_hop_strips_client_default_headers_and_auth(): void
    {
        $client = $this->client(
            [
                new Response(307, ['Location' => 'http://datanode:9864/webhdfs/v1/data/file.txt?op=OPEN'], ''),
                new Response(200, [], 'hello'),
            ],
            [
                'headers' => ['Authorization' => 'Basic ' . base64_encode('php:secret')],
                'auth' => ['php', 'secret'],
            ],
        );

        self::assertSame('hello', $client->read('/data/file.txt'));
        self::assertCount(2, $this->history);

        $first = $this->history[0]['request'];
        $second = $this->history[1]['request'];

        self::assertTrue($first->hasHeader('Authorization'));
        self::assertFalse($second->hasHeader('Authorization'));
        self::assertSame('php', Query::parse($second->getUri()->getQuery())['user.name']);
    }

    public function test_debug_log_omits_delegation_token_and_user_name(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $client = $this->client(
            [new Response(200, [], 'hello')],
            [],
            $logger,
            ['delegation_token' => 'super-secret-token'],
        );

        self::assertSame('hello', $client->read('/data/file.txt'));

        self::assertNotEmpty($logger->records);
        self::assertStringNotContainsString('super-secret-token', json_encode($logger->records));
        self::assertStringNotContainsString('user.name', json_encode($logger->records));
        self::assertSame(
            'http://namenode.local:9870/webhdfs/v1/data/file.txt',
            $logger->records[0]['context']['uri'],
        );
    }

    public function test_rename_returns_boolean(): void
    {
        $client = $this->client([new Response(200, [], json_encode(['boolean' => true]))]);

        self::assertTrue($client->rename('/data/a', '/data/b'));

        $request = $this->history[0]['request'];
        self::assertSame('PUT', $request->getMethod());

        $query = Query::parse($request->getUri()->getQuery());
        self::assertSame('RENAME', $query['op']);
        self::assertSame('/data/b', $query['destination']);
        self::assertSame('php', $query['user.name']);
    }

    public function test_remote_exception_is_mapped_to_request_exception(): void
    {
        $client = $this->client([
            new Response(404, [], json_encode(['RemoteException' => ['message' => 'File does not exist']])),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('File does not exist');
        $client->fileStatus('/missing');
    }

    public function test_write_resends_body_on_307(): void
    {
        $client = $this->client([
            new Response(307, ['Location' => 'http://datanode:9864/webhdfs/v1/data/file.txt?op=CREATE&overwrite=true'], ''),
            new Response(201, [], ''),
        ]);

        self::assertTrue($client->write('/data/file.txt', 'payload', true));
        self::assertCount(2, $this->history);

        $first = $this->history[0]['request'];
        $second = $this->history[1]['request'];

        self::assertSame('PUT', $first->getMethod());
        $query = Query::parse($first->getUri()->getQuery());
        self::assertSame('CREATE', $query['op']);
        self::assertSame('true', $query['overwrite']);
        self::assertSame('payload', (string) $first->getBody());
        self::assertSame('payload', (string) $second->getBody());
        self::assertFalse($second->hasHeader('Authorization'));
    }
}
