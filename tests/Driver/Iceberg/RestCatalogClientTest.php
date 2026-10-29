<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Iceberg;

use Erikwang2013\BigData\Exception\RequestException;
use Erikwang2013\BigData\Driver\Iceberg\RestCatalogClient;
use Erikwang2013\BigData\Driver\Iceberg\RestCatalogConfig;
use Erikwang2013\BigData\Driver\Iceberg\TableMetadata;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class RestCatalogClientTest extends TestCase
{
    private array $history = [];

    private function client(array $responses, array $config = []): RestCatalogClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new RestCatalogClient(
            RestCatalogConfig::fromArray($config + ['base_url' => 'https://catalog.example.com']),
            new Client(['handler' => $stack]),
        );
    }

    private function schema(): array
    {
        return [
            'type' => 'struct',
            'schema-id' => 0,
            'fields' => [
                ['id' => 1, 'name' => 'id', 'required' => true, 'type' => 'long'],
                ['id' => 2, 'name' => 'event', 'required' => false, 'type' => 'string'],
            ],
        ];
    }

    private function metadata(): array
    {
        return [
            'format-version' => 2,
            'table-uuid' => '9c12f4a8-7f3b-4a9e-8c1d-2b3a4c5d6e7f',
            'location' => 's3://bucket/warehouse/analytics/events',
            'last-updated-ms' => 1700000000000,
            'schema' => $this->schema(),
            'partition-spec' => [
                ['field-id' => 1000, 'source-id' => 2, 'name' => 'event', 'transform' => 'identity'],
            ],
            'snapshots' => [
                [
                    'snapshot-id' => 3051729675574597004,
                    'timestamp-ms' => 1700000001000,
                    'manifest-list' => 's3://bucket/warehouse/analytics/events/metadata/snap-3051729675574597004.avro',
                    'summary' => ['operation' => 'append'],
                ],
            ],
            'properties' => ['owner' => 'data-team'],
            'current-snapshot-id' => 3051729675574597004,
        ];
    }

    public function test_config_endpoint_parsed(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'defaults' => ['warehouse' => 's3://bucket/warehouse'],
                'overrides' => ['prefix' => 'main'],
            ])),
        ], ['warehouse' => 's3://bucket/warehouse']);

        $config = $client->config();

        self::assertSame(['warehouse' => 's3://bucket/warehouse'], $config['defaults']);
        self::assertSame(['prefix' => 'main'], $config['overrides']);

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/v1/config', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame('s3://bucket/warehouse', $query['warehouse']);
    }

    public function test_namespaces_follow_page_token(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'namespaces' => [['analytics']],
                'nextPageToken' => 'next-page',
            ])),
            new Response(200, [], json_encode([
                'namespaces' => [['raw'], ['staging']],
            ])),
        ]);

        self::assertSame([['analytics'], ['raw'], ['staging']], $client->namespaces('parent_ns'));
        self::assertCount(2, $this->history);

        $first = $this->history[0]['request'];
        self::assertSame('/v1/namespaces', $first->getUri()->getPath());
        parse_str($first->getUri()->getQuery(), $firstQuery);
        self::assertSame('parent_ns', $firstQuery['parent']);
        self::assertSame('100', $firstQuery['pageSize']);
        self::assertArrayNotHasKey('pageToken', $firstQuery);

        $second = $this->history[1]['request'];
        parse_str($second->getUri()->getQuery(), $secondQuery);
        self::assertSame('next-page', $secondQuery['pageToken']);
        self::assertSame('100', $secondQuery['pageSize']);
    }

    public function test_pagination_accepts_kebab_case_next_page_token(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'namespaces' => [['analytics']],
                'next-page-token' => 'page-2',
            ])),
            new Response(200, [], json_encode(['namespaces' => [['raw']]])),
        ]);

        self::assertSame([['analytics'], ['raw']], $client->namespaces());

        parse_str($this->history[1]['request']->getUri()->getQuery(), $query);
        self::assertSame('page-2', $query['pageToken']);
    }

    public function test_load_table_maps_metadata(): void
    {
        $metadata = $this->metadata();
        $client = $this->client([
            new Response(200, [], json_encode([
                'metadata' => $metadata,
                'config' => ['client.region' => 'us-east-1'],
            ])),
        ]);

        $table = $client->loadTable('analytics', 'events');

        self::assertInstanceOf(TableMetadata::class, $table);
        self::assertSame('s3://bucket/warehouse/analytics/events', $table->location());
        self::assertSame($metadata['schema'], $table->schema());
        self::assertSame($metadata['partition-spec'], $table->partitionSpec());
        self::assertSame($metadata['snapshots'], $table->snapshots());
        self::assertSame($metadata['properties'], $table->properties());
        self::assertSame(3051729675574597004, $table->currentSnapshotId());

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/v1/namespaces/analytics/tables/events', $request->getUri()->getPath());
    }

    public function test_prefix_applies_only_to_resource_paths(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode(['access_token' => 'oauth-token', 'expires_in' => 3600])),
            new Response(200, [], json_encode(['defaults' => [], 'overrides' => []])),
            new Response(200, [], json_encode(['namespaces' => [['analytics']]])),
        ], ['prefix' => 'main', 'client_id' => 'client', 'client_secret' => 'secret']);

        $client->config();
        self::assertSame([['analytics']], $client->namespaces());

        self::assertSame('/v1/oauth/tokens', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('/v1/config', $this->history[1]['request']->getUri()->getPath());
        self::assertSame('/v1/main/namespaces', $this->history[2]['request']->getUri()->getPath());
    }

    public function test_table_exists_returns_false_on_404(): void
    {
        $client = $this->client([
            new Response(404, [], json_encode([
                'error' => ['message' => 'Table does not exist: analytics.missing', 'type' => 'NoSuchTableException'],
            ])),
        ]);

        self::assertFalse($client->tableExists('analytics', 'missing'));

        $request = $this->history[0]['request'];
        self::assertSame('HEAD', $request->getMethod());
        self::assertSame('/v1/namespaces/analytics/tables/missing', $request->getUri()->getPath());
    }

    public function test_table_exists_rethrows_non_404(): void
    {
        $client = $this->client([new Response(500, [], 'boom')]);

        try {
            $client->tableExists('analytics', 'events');
            self::fail('Expected a RequestException for HTTP 500.');
        } catch (RequestException $e) {
            self::assertSame(500, $e->statusCode);
            self::assertSame('boom', $e->body);
        }
    }

    public function test_table_exists_returns_true_on_200(): void
    {
        $client = $this->client([new Response(200)]);

        self::assertTrue($client->tableExists('analytics', 'events'));
    }

    public function test_oauth_token_is_fetched_and_reused(): void
    {
        $client = $this->client([
            new Response(200, [], json_encode([
                'access_token' => 'oauth-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ])),
            new Response(200, [], json_encode(['namespaces' => [['analytics']]])),
            new Response(200, [], json_encode(['namespaces' => [['analytics']]])),
        ], [
            'client_id' => 'client',
            'client_secret' => 'secret',
        ]);

        self::assertSame([['analytics']], $client->namespaces());
        self::assertSame([['analytics']], $client->namespaces());
        self::assertCount(3, $this->history);

        $tokenRequest = $this->history[0]['request'];
        self::assertSame('POST', $tokenRequest->getMethod());
        self::assertSame('/v1/oauth/tokens', $tokenRequest->getUri()->getPath());
        self::assertStringContainsString(
            'application/x-www-form-urlencoded',
            $tokenRequest->getHeaderLine('Content-Type'),
        );
        parse_str((string) $tokenRequest->getBody(), $form);
        self::assertSame('client_credentials', $form['grant_type']);
        self::assertSame('client', $form['client_id']);
        self::assertSame('secret', $form['client_secret']);
        self::assertSame('catalog', $form['scope']);

        self::assertSame('Bearer oauth-token', $this->history[1]['request']->getHeaderLine('Authorization'));
        self::assertSame('Bearer oauth-token', $this->history[2]['request']->getHeaderLine('Authorization'));
    }

    public function test_create_table_posts_schema(): void
    {
        $schema = $this->schema();
        $client = $this->client([
            new Response(200, [], json_encode([
                'metadata' => $this->metadata(),
                'config' => [],
            ])),
        ]);

        $response = $client->createTable('analytics', 'events', $schema, ['owner' => 'data-team']);

        self::assertSame('s3://bucket/warehouse/analytics/events', $response['metadata']['location']);

        $request = $this->history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/namespaces/analytics/tables', $request->getUri()->getPath());
        self::assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'name' => 'events',
            'schema' => $schema,
            'properties' => ['owner' => 'data-team'],
        ], json_decode((string) $request->getBody(), true));
    }
}
