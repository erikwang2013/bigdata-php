<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Flink;

use Erikwang2013\BigData\Driver\Flink\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class JobClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private FlinkConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function jobs(): array
    {
        return $this->json('GET', '/jobs')['jobs'] ?? [];
    }

    public function job(string $id): array
    {
        return $this->json('GET', '/jobs/' . rawurlencode($id));
    }

    public function cancel(string $id): void
    {
        Http::request('PATCH', $this->uri('PATCH', '/jobs/' . rawurlencode($id) . '?mode=cancel'), $this->options());
    }

    private function json(string $method, string $path): array
    {
        return Http::json($method, $this->uri($method, $path), $this->options());
    }

    private function uri(string $method, string $path): string
    {
        $uri = $this->config->baseUrl . $path;
        $this->logger->debug('Flink request', ['method' => $method, 'uri' => $uri]);

        return $uri;
    }

    private function options(): array
    {
        $options = [
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
        ];

        if ($this->config->token !== null && $this->config->token !== '') {
            $options['headers'] = ['Authorization' => 'Bearer ' . $this->config->token];
        }

        return $options;
    }
}
