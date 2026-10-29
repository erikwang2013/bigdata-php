<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Paimon;

use Erikwang2013\BigData\Driver\Paimon\WebHdfsConfig;
use Erikwang2013\BigData\Driver\Paimon\WebHdfsStorage;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class WebHdfsStorageTest extends TestCase
{
    private array $history = [];

    private function storage(
        array $responses,
        array $clientConfig = [],
        ?LoggerInterface $logger = null,
    ): WebHdfsStorage {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new WebHdfsStorage(
            WebHdfsConfig::fromArray(['host' => 'namenode.local', 'user' => 'php']),
            new Client($clientConfig + ['handler' => $stack]),
            $logger,
        );
    }

    public function test_webhdfs_storage_urls(): void
    {
        $storage = $this->storage([
            new Response(200, [], json_encode([
                'FileStatuses' => ['FileStatus' => [
                    ['pathSuffix' => 'schema-0', 'type' => 'FILE'],
                    ['pathSuffix' => 'schema-1', 'type' => 'FILE'],
                ]],
            ])),
            new Response(307, [
                'Location' => 'http://datanode.local:9864/webhdfs/v1/warehouse/tbl/schema/schema-1?op=OPEN',
            ], ''),
            new Response(200, [], '{"id":1}'),
        ]);

        self::assertSame(
            ['schema-0', 'schema-1'],
            $storage->list('/warehouse/tbl/schema'),
        );
        self::assertSame(
            '{"id":1}',
            $storage->read('/warehouse/tbl/schema/schema-1'),
        );

        $listRequest = $this->history[0]['request'];
        self::assertSame('GET', $listRequest->getMethod());
        self::assertSame(
            'http://namenode.local:9870/webhdfs/v1/warehouse/tbl/schema?op=LISTSTATUS&user.name=php',
            (string) $listRequest->getUri(),
        );

        $openRequest = $this->history[1]['request'];
        self::assertSame(
            'http://namenode.local:9870/webhdfs/v1/warehouse/tbl/schema/schema-1?op=OPEN&user.name=php',
            (string) $openRequest->getUri(),
        );

        $redirect = $this->history[2]['request'];
        self::assertSame('datanode.local', $redirect->getUri()->getHost());
        $query = Query::parse($redirect->getUri()->getQuery());
        self::assertSame('OPEN', $query['op']);
        self::assertSame('php', $query['user.name']);
    }

    public function test_redirect_hop_strips_client_default_headers_and_auth(): void
    {
        $storage = $this->storage(
            [
                new Response(307, [
                    'Location' => 'http://datanode.local:9864/webhdfs/v1/warehouse/tbl/schema/schema-1?op=OPEN',
                ], ''),
                new Response(200, [], '{"id":1}'),
            ],
            [
                'headers' => ['Authorization' => 'Basic ' . base64_encode('hdfs:s3cret')],
                'auth' => ['hdfs', 's3cret'],
            ],
        );

        self::assertSame('{"id":1}', $storage->read('/warehouse/tbl/schema/schema-1'));

        self::assertCount(2, $this->history);

        $first = $this->history[0]['request'];
        $second = $this->history[1]['request'];

        self::assertNotSame('', $first->getHeaderLine('Authorization'));
        self::assertFalse($second->hasHeader('Authorization'));
        self::assertSame('php', Query::parse($second->getUri()->getQuery())['user.name']);
    }

    public function test_debug_log_records_method_and_uri_without_credentials(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $storage = $this->storage(
            [
                new Response(307, [
                    'Location' => 'http://datanode.local:9864/webhdfs/v1/warehouse/tbl/schema/schema-1?op=OPEN',
                ], ''),
                new Response(200, [], '{"id":1}'),
            ],
            [
                'headers' => ['Authorization' => 'Basic ' . base64_encode('hdfs:s3cret')],
                'auth' => ['hdfs', 's3cret'],
            ],
            $logger,
        );

        $storage->read('/warehouse/tbl/schema/schema-1');

        self::assertCount(2, $logger->records);
        foreach ($logger->records as $record) {
            self::assertSame(LogLevel::DEBUG, $record['level']);
            self::assertSame('GET', $record['context']['method']);
            self::assertStringContainsString('op=OPEN', $record['context']['uri']);
        }

        $logs = serialize($logger->records);
        self::assertStringNotContainsString('Authorization', $logs);
        self::assertStringNotContainsString('Basic ', $logs);
        self::assertStringNotContainsString('s3cret', $logs);
    }
}
