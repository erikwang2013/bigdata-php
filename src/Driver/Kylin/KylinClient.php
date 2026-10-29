<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Kylin;

use Erikwang2013\BigData\Driver\Kylin\Exception\RequestException;
use Erikwang2013\BigData\Driver\Kylin\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class KylinClient
{
    private readonly ClientInterface $http;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly KylinConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function query(string $sql, ?string $project = null, ?int $limit = null, int $offset = 0): ResultSet
    {
        $payload = $this->json('POST', '/api/query', [
            'json' => [
                'sql' => $sql,
                'project' => $project ?? $this->config->project,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);

        if (!empty($payload['isException'])) {
            throw new RequestException((string) ($payload['exceptionMessage'] ?? 'Kylin query failed'));
        }

        return new ResultSet($payload['columnMetas'] ?? [], $payload['results'] ?? [], [$payload]);
    }

    public function cubes(int $offset = 0, int $limit = 100): array
    {
        $payload = $this->json('GET', '/api/cubes', [
            'query' => $this->scoped(['offset' => $offset, 'limit' => $limit]),
        ]);

        return $payload['cubes'] ?? $payload;
    }

    public function models(int $offset = 0, int $limit = 100): array
    {
        $payload = $this->json('GET', '/api/models', [
            'query' => $this->scoped(['offset' => $offset, 'limit' => $limit]),
        ]);

        return $payload['models'] ?? $payload;
    }

    public function jobs(array $filters = []): array
    {
        $payload = $this->json('GET', '/api/jobs', ['query' => $this->scoped($filters)]);

        return $payload['jobs'] ?? $payload;
    }

    public function buildCube(string $cube, string $startTime, string $endTime, string $buildType = 'BUILD'): array
    {
        return $this->json('PUT', '/api/cubes/' . rawurlencode($cube) . '/rebuild', [
            'json' => ['startTime' => $startTime, 'endTime' => $endTime, 'buildType' => $buildType],
        ]);
    }

    private function scoped(array $query): array
    {
        // An explicit filter wins over the configured default project.
        return $this->config->project !== null ? $query + ['project' => $this->config->project] : $query;
    }

    private function json(string $method, string $path, array $options = []): array
    {
        return Http::json($method, $this->uri($method, $path), $this->options($options));
    }

    private function uri(string $method, string $path): string
    {
        $uri = $this->config->baseUri() . $this->config->basePath . $path;
        $this->logger->debug('Kylin request', ['method' => $method, 'uri' => $uri]);

        return $uri;
    }

    private function options(array $extra = []): array
    {
        $options = array_merge([
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
        ], $extra);

        $options['headers'] = array_merge($this->headers(), $extra['headers'] ?? []);

        return $options;
    }

    private function headers(): array
    {
        return ['Authorization' => 'Basic ' . base64_encode(
            $this->config->username . ':' . $this->config->password,
        )];
    }
}
