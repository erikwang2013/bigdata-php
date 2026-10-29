<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Airflow;

use Erikwang2013\BigData\Driver\Airflow\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class AirflowClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private AirflowConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function dags(int $limit = 100, int $offset = 0): array
    {
        return $this->get('/dags' . $this->pagination($limit, $offset));
    }

    public function dag(string $dagId): array
    {
        return $this->get('/dags/' . rawurlencode($dagId));
    }

    public function triggerDag(string $dagId, array $conf = [], ?string $dagRunId = null): array
    {
        // Airflow expects conf as a JSON object; an empty PHP array would encode as [].
        $payload = ['conf' => (object) $conf];
        if ($dagRunId !== null) {
            $payload['dag_run_id'] = $dagRunId;
        }

        return $this->call('POST', '/dags/' . rawurlencode($dagId) . '/dagRuns', ['json' => $payload]);
    }

    public function dagRuns(string $dagId, int $limit = 100, int $offset = 0): array
    {
        return $this->get(
            '/dags/' . rawurlencode($dagId) . '/dagRuns' . $this->pagination($limit, $offset),
        );
    }

    public function dagRun(string $dagId, string $dagRunId): array
    {
        return $this->get(
            '/dags/' . rawurlencode($dagId) . '/dagRuns/' . rawurlencode($dagRunId),
        );
    }

    public function taskInstances(string $dagId, string $dagRunId): array
    {
        return $this->get(
            '/dags/' . rawurlencode($dagId) . '/dagRuns/' . rawurlencode($dagRunId) . '/taskInstances',
        );
    }

    public function taskLog(string $dagId, string $dagRunId, string $taskId, int $tryNumber = 1): string
    {
        $uri = $this->uri(sprintf(
            '/dags/%s/dagRuns/%s/taskInstances/%s/logs/%d',
            rawurlencode($dagId),
            rawurlencode($dagRunId),
            rawurlencode($taskId),
            $tryNumber,
        ));

        $this->logger->debug('Airflow request', ['method' => 'GET', 'uri' => $uri]);

        return (string) Http::request('GET', $uri, $this->options([
            'headers' => ['Accept' => 'text/plain'],
        ]))->getBody();
    }

    public function variables(int $limit = 100, int $offset = 0): array
    {
        return $this->get('/variables' . $this->pagination($limit, $offset));
    }

    public function setVariable(string $key, string $value): array
    {
        return $this->call('POST', '/variables', ['json' => ['key' => $key, 'value' => $value]]);
    }

    public function deleteVariable(string $key): void
    {
        $uri = $this->uri('/variables/' . rawurlencode($key));

        $this->logger->debug('Airflow request', ['method' => 'DELETE', 'uri' => $uri]);

        Http::request('DELETE', $uri, $this->options());
    }

    private function get(string $path): array
    {
        return $this->call('GET', $path);
    }

    private function call(string $method, string $path, array $extra = []): array
    {
        $uri = $this->uri($path);

        $this->logger->debug('Airflow request', ['method' => $method, 'uri' => $uri]);

        return Http::json($method, $uri, $this->options($extra));
    }

    private function uri(string $path): string
    {
        return rtrim($this->config->baseUri(), '/') . $path;
    }

    private function pagination(int $limit, int $offset): string
    {
        return sprintf('?limit=%d&offset=%d', $limit, $offset);
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
        $headers = ['Accept' => 'application/json'];

        // Bearer token wins over basic auth when both are configured.
        if ($this->config->token !== null && $this->config->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->config->token;
        } elseif ($this->config->username !== null && $this->config->username !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode(
                $this->config->username . ':' . ($this->config->password ?? ''),
            );
        }

        return $headers;
    }
}
