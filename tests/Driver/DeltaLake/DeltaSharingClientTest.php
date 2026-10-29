<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\DeltaLake;

use Erikwang2013\BigData\Driver\DeltaLake\DeltaSharingClient;
use Erikwang2013\BigData\Driver\DeltaLake\DeltaSharingConfig;
use Erikwang2013\BigData\Driver\DeltaLake\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DeltaSharingClientTest extends TestCase
{
    private const ENDPOINT = 'https://sharing.example.com/delta-sharing';

    private array $history = [];

    private function client(array $responses): DeltaSharingClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new DeltaSharingClient(
            DeltaSharingConfig::fromArray(['endpoint' => self::ENDPOINT, 'token' => 'secret-token']),
            new Client(['handler' => $stack]),
        );
    }

    public function test_shares_parsed(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['items' => [
                ['name' => 'share1', 'id' => 'share-1'],
                ['name' => 'share2', 'id' => 'share-2'],
            ]])),
        ]);

        self::assertSame([
            ['name' => 'share1', 'id' => 'share-1'],
            ['name' => 'share2', 'id' => 'share-2'],
        ], $client->shares());

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::ENDPOINT . '/shares', (string) $request->getUri());
    }

    public function test_schemas_and_tables_parsed(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['items' => [
                ['name' => 'default', 'share' => 'share1'],
            ]])),
            new Response(200, [], json_encode(['items' => [
                ['name' => 'orders', 'share' => 'share1', 'schema' => 'default'],
                ['name' => 'customers', 'share' => 'share1', 'schema' => 'default'],
            ]])),
        ]);

        self::assertSame(
            [['name' => 'default', 'share' => 'share1']],
            $client->schemas('share1'),
        );
        self::assertSame(
            [
                ['name' => 'orders', 'share' => 'share1', 'schema' => 'default'],
                ['name' => 'customers', 'share' => 'share1', 'schema' => 'default'],
            ],
            $client->tables('share1', 'default'),
        );

        self::assertSame(
            self::ENDPOINT . '/shares/share1/schemas',
            (string) $this->history[0]['request']->getUri(),
        );
        self::assertSame(
            self::ENDPOINT . '/shares/share1/schemas/default/tables',
            (string) $this->history[1]['request']->getUri(),
        );
    }

    public function test_query_parses_ndjson_protocol_metadata_and_files(): void
    {
        $client = $this->client([new Response(200, [], $this->ndjson())]);

        $result = $client->query('share1', 'default', 'orders');

        self::assertSame(['minReaderVersion' => 1, 'minWriterVersion' => 2], $result->protocol());
        self::assertSame([
            'id' => 'table-1',
            'format' => ['provider' => 'parquet'],
            'schemaString' => '{"type":"struct"}',
            'partitionColumns' => ['date'],
        ], $result->metadata());
        self::assertSame([
            [
                'url' => 'https://storage.example.com/orders/part-00000.parquet',
                'id' => 'file-1',
                'partitionValues' => ['date' => '2026-10-29'],
                'size' => 1234,
                'stats' => '{"numRecords":3}',
            ],
        ], $result->files());

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            self::ENDPOINT . '/shares/share1/schemas/default/tables/orders/query',
            (string) $request->getUri(),
        );
    }

    public function test_query_sends_limit_and_predicate_hints(): void
    {
        $client = $this->client([new Response(200, [], $this->ndjson())]);

        $client->query('share1', 'default', 'orders', [
            'predicateHints' => ["date = '2026-10-29'"],
            'limit' => 100,
            'startingVersion' => 3,
        ]);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);

        self::assertSame(["date = '2026-10-29'"], $body['predicateHints']);
        self::assertSame(100, $body['limitHint']);
        self::assertSame(3, $body['startingVersion']);
        self::assertArrayNotHasKey('limit', $body);
        self::assertArrayNotHasKey('version', $body);
        self::assertArrayNotHasKey('timestamp', $body);
        self::assertArrayNotHasKey('endingVersion', $body);
    }

    public function test_bearer_token_sent(): void
    {
        $client = $this->client([new Response(200, [], json_encode(['items' => []]))]);

        $client->shares();

        self::assertSame(
            'Bearer secret-token',
            $this->history[0]['request']->getHeaderLine('Authorization'),
        );
    }

    public function test_error_response_throws(): void
    {
        $client = $this->client([
            new Response(403, [], '{"errorCode":"PERMISSION_DENIED","message":"not authorized"}'),
        ]);

        try {
            $client->shares();
            self::fail('Expected a RequestException for a non-2xx response.');
        } catch (RequestException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertStringContainsString('PERMISSION_DENIED', $e->body);
            self::assertStringContainsString('403', $e->getMessage());
        }
    }

    public function test_stream_error_message_throws(): void
    {
        $ndjson = implode("\n", array_map('json_encode', [
            ['protocol' => ['minReaderVersion' => 1]],
            ['endStreamAction' => ['errorMessage' => 'Failed to read table version.']],
        ]));

        $client = $this->client([new Response(200, [], $ndjson)]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Failed to read table version.');
        $client->query('share1', 'default', 'orders');
    }

    public function test_table_metadata_parsed(): void
    {
        $metadata = [
            'id' => 'table-1',
            'name' => 'orders',
            'format' => ['provider' => 'parquet'],
            'schemaString' => '{"type":"struct"}',
            'partitionColumns' => ['date'],
        ];

        $ndjson = implode("\n", array_map('json_encode', [
            ['protocol' => ['minReaderVersion' => 1]],
            ['metaData' => $metadata],
        ]));

        $client = $this->client([new Response(200, [], $ndjson)]);

        self::assertSame($metadata, $client->tableMetadata('share1', 'default', 'orders'));
        self::assertSame(
            self::ENDPOINT . '/shares/share1/schemas/default/tables/orders/metadata',
            (string) $this->history[0]['request']->getUri(),
        );
    }

    private function ndjson(): string
    {
        $lines = [
            ['protocol' => ['minReaderVersion' => 1, 'minWriterVersion' => 2]],
            ['metaData' => [
                'id' => 'table-1',
                'format' => ['provider' => 'parquet'],
                'schemaString' => '{"type":"struct"}',
                'partitionColumns' => ['date'],
            ]],
            ['file' => [
                'url' => 'https://storage.example.com/orders/part-00000.parquet',
                'id' => 'file-1',
                'partitionValues' => ['date' => '2026-10-29'],
                'size' => 1234,
                'stats' => '{"numRecords":3}',
            ]],
        ];

        return implode("\n", array_map(
            static fn (array $line): string => json_encode($line, JSON_THROW_ON_ERROR),
            $lines,
        ));
    }
}
