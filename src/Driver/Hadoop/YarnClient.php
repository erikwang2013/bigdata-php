<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hadoop;

use Erikwang2013\BigData\Driver\Hadoop\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class YarnClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private YarnConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function clusterMetrics(): array
    {
        $data = $this->json('GET', $this->uri('/ws/v1/cluster/metrics'));

        return $data['clusterMetrics'] ?? [];
    }

    public function apps(array $filters = []): array
    {
        $data = $this->json('GET', $this->uri('/ws/v1/cluster/apps', $filters));

        return $data['apps']['app'] ?? [];
    }

    public function app(string $id): array
    {
        $data = $this->json('GET', $this->uri('/ws/v1/cluster/apps/' . rawurlencode($id)));

        return $data['app'] ?? [];
    }

    public function killApp(string $id): void
    {
        $uri = $this->uri('/ws/v1/cluster/apps/' . rawurlencode($id) . '/state');
        $this->logger->debug('YARN request', ['method' => 'PUT', 'uri' => $uri]);

        Http::request('PUT', $uri, $this->options(['json' => ['state' => 'KILLED']]));
    }

    private function json(string $method, string $uri): array
    {
        $this->logger->debug('YARN request', ['method' => $method, 'uri' => $uri]);

        return Http::json($method, $uri, $this->options());
    }

    private function uri(string $path, array $params = []): string
    {
        $params['user.name'] = $this->config->user;

        return $this->config->baseUri()
            . $path
            . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function options(array $extra = []): array
    {
        return array_merge([
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
        ], $extra);
    }
}
