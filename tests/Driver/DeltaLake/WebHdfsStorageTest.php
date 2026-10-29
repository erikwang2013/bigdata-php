<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\DeltaLake;

use Erikwang2013\BigData\Driver\DeltaLake\WebHdfsConfig;
use Erikwang2013\BigData\Driver\DeltaLake\WebHdfsStorage;
use Erikwang2013\BigData\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class WebHdfsStorageTest extends TestCase
{
    private array $history = [];

    private function storage(array $responses): WebHdfsStorage
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new WebHdfsStorage(
            WebHdfsConfig::fromArray(['host' => 'namenode.local', 'user' => 'php']),
            new Client(['handler' => $stack]),
        );
    }

    public function test_lists_and_reads_delta_log_over_webhdfs(): void
    {
        $storage = $this->storage([
            new Response(200, [], json_encode([
                'FileStatuses' => ['FileStatus' => [
                    ['pathSuffix' => '00000000000000000000.json', 'type' => 'FILE'],
                    ['pathSuffix' => '_last_checkpoint', 'type' => 'FILE'],
                ]],
            ])),
            new Response(307, [
                'Location' => 'http://datanode.local:9864/webhdfs/v1/warehouse/tbl/_delta_log/00000000000000000000.json?op=OPEN',
            ], ''),
            new Response(200, [], '{"protocol":{"minReaderVersion":1}}'),
        ]);

        self::assertSame(
            ['00000000000000000000.json', '_last_checkpoint'],
            $storage->list('/warehouse/tbl/_delta_log'),
        );
        self::assertSame(
            '{"protocol":{"minReaderVersion":1}}',
            $storage->read('/warehouse/tbl/_delta_log/00000000000000000000.json'),
        );

        self::assertSame(
            'http://namenode.local:9870/webhdfs/v1/warehouse/tbl/_delta_log?op=LISTSTATUS&user.name=php',
            (string) $this->history[0]['request']->getUri(),
        );

        $redirect = $this->history[2]['request'];
        self::assertSame('datanode.local', $redirect->getUri()->getHost());
        self::assertSame('php', Query::parse($redirect->getUri()->getQuery())['user.name']);
    }

    public function test_http_error_throws_shared_request_exception(): void
    {
        $storage = $this->storage([
            new Response(200, [], json_encode(['FileStatuses' => ['FileStatus' => []]])),
            new Response(403, [], '{"RemoteException":{}}'),
        ]);

        $storage->list('/warehouse/tbl/_delta_log');

        try {
            $storage->read('/warehouse/tbl/_delta_log/00000000000000000000.json');
            self::fail('Expected a RequestException for a 403 response.');
        } catch (RequestException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertStringContainsString('403', $e->getMessage());
        }
    }

    public function test_final_non_2xx_after_redirect_follow_throws(): void
    {
        $storage = $this->storage([new Response(302, [], 'moved')]);

        try {
            $storage->read('/warehouse/tbl/_delta_log/00000000000000000000.json');
            self::fail('Expected a RequestException for an unfollowed 302 response.');
        } catch (RequestException $e) {
            self::assertSame(302, $e->statusCode);
        }
    }
}
