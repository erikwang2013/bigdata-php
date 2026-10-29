<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Iceberg;

use Erikwang2013\BigData\Exception\RequestException;
use Erikwang2013\BigData\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class RestCatalogClient
{
    private const PAGE_SIZE = 100;

    private ClientInterface $http;

    private LoggerInterface $logger;

    private ?string $cachedToken = null;

    private float $tokenExpiresAt = 0.0;

    public function __construct(
        private RestCatalogConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function config(): array
    {
        $query = [];
        if ($this->config->warehouse !== null && $this->config->warehouse !== '') {
            $query['warehouse'] = $this->config->warehouse;
        }

        return $this->json('GET', '/config', $query);
    }

    public function namespaces(?string $parent = null): array
    {
        $query = [];
        if ($parent !== null && $parent !== '') {
            $query['parent'] = $parent;
        }

        return $this->paginate($this->namespacesPath(), $query, 'namespaces');
    }

    public function createNamespace(string $namespace, array $properties = []): array
    {
        return $this->json('POST', $this->namespacesPath(), [], [
            'json' => [
                'namespace' => [$namespace],
                'properties' => $properties === [] ? new \stdClass() : $properties,
            ],
        ]);
    }

    public function loadNamespace(string $namespace): array
    {
        return $this->json('GET', $this->namespacePath($namespace));
    }

    public function dropNamespace(string $namespace): void
    {
        $this->request('DELETE', $this->namespacePath($namespace));
    }

    public function tables(string $namespace): array
    {
        return $this->paginate($this->namespacePath($namespace) . '/tables', [], 'identifiers');
    }

    public function createTable(string $namespace, string $table, array $schema, array $properties = []): array
    {
        return $this->json('POST', $this->namespacePath($namespace) . '/tables', [], [
            'json' => [
                'name' => $table,
                'schema' => $schema,
                'properties' => $properties === [] ? new \stdClass() : $properties,
            ],
        ]);
    }

    public function loadTable(string $namespace, string $table): TableMetadata
    {
        $payload = $this->json('GET', $this->tablePath($namespace, $table));

        if (!isset($payload['metadata']) || !is_array($payload['metadata'])) {
            throw new RequestException('Iceberg loadTable response is missing "metadata".');
        }

        return TableMetadata::fromArray($payload['metadata']);
    }

    public function tableExists(string $namespace, string $table): bool
    {
        try {
            $this->request('HEAD', $this->tablePath($namespace, $table));

            return true;
        } catch (RequestException $e) {
            if ($e->statusCode === 404) {
                return false;
            }

            throw $e;
        }
    }

    public function dropTable(string $namespace, string $table): void
    {
        $this->request('DELETE', $this->tablePath($namespace, $table));
    }

    private function paginate(string $path, array $query, string $key): array
    {
        $items = [];
        $token = null;

        do {
            $page = $query + ['pageSize' => self::PAGE_SIZE];
            if ($token !== null) {
                $page['pageToken'] = $token;
            }

            $payload = $this->json('GET', $path, $page);
            foreach ($payload[$key] ?? [] as $item) {
                $items[] = $item;
            }

            // The spec field is "next-page-token"; "nextPageToken" is accepted for compatibility.
            $token = $payload['next-page-token'] ?? $payload['nextPageToken'] ?? null;
        } while ($token !== null && $token !== '');

        return $items;
    }

    private function json(string $method, string $path, array $query = [], array $extra = []): array
    {
        $uri = $this->config->baseUri() . $path;
        $this->logger->debug('Iceberg request', ['method' => $method, 'uri' => $uri]);

        if ($query !== []) {
            $extra['query'] = $query;
        }

        return Http::json($method, $uri, $this->options($extra));
    }

    private function request(string $method, string $path): ResponseInterface
    {
        $uri = $this->config->baseUri() . $path;
        $this->logger->debug('Iceberg request', ['method' => $method, 'uri' => $uri]);

        return Http::request($method, $uri, $this->options());
    }

    private function options(array $extra = [], bool $authenticated = true): array
    {
        $options = array_merge([
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
        ], $extra);

        $options['headers'] = array_merge(
            $authenticated ? $this->headers() : [],
            $extra['headers'] ?? [],
        );

        return $options;
    }

    private function headers(): array
    {
        $token = $this->token();

        return $token === null ? [] : ['Authorization' => 'Bearer ' . $token];
    }

    private function token(): ?string
    {
        if ($this->config->token !== null && $this->config->token !== '') {
            return $this->config->token;
        }

        if ($this->config->clientId === null || $this->config->clientId === ''
            || $this->config->clientSecret === null || $this->config->clientSecret === ''
        ) {
            return null;
        }

        if ($this->cachedToken !== null && microtime(true) < $this->tokenExpiresAt) {
            return $this->cachedToken;
        }

        $uri = $this->config->baseUri() . '/oauth/tokens';
        $this->logger->debug('Iceberg request', ['method' => 'POST', 'uri' => $uri]);

        $payload = Http::json('POST', $uri, $this->options([
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config->clientId,
                'client_secret' => $this->config->clientSecret,
                'scope' => $this->config->scope,
            ],
        ], false));

        if (!isset($payload['access_token'])) {
            throw new RequestException('Iceberg OAuth token response is missing "access_token".');
        }

        $this->cachedToken = (string) $payload['access_token'];
        $expiresIn = (int) ($payload['expires_in'] ?? 0);
        $this->tokenExpiresAt = microtime(true) + max(0, $expiresIn - 30);

        return $this->cachedToken;
    }

    private function namespacesPath(): string
    {
        $prefix = $this->config->prefix;

        return ($prefix !== null && $prefix !== '' ? '/' . $prefix : '') . '/namespaces';
    }

    private function namespacePath(string $namespace): string
    {
        return $this->namespacesPath() . '/' . rawurlencode($namespace);
    }

    private function tablePath(string $namespace, string $table): string
    {
        return $this->namespacePath($namespace) . '/tables/' . rawurlencode($table);
    }
}
