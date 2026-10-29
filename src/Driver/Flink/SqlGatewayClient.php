<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Flink;

use Erikwang2013\BigData\Driver\Flink\Exception\RequestException;
use Erikwang2013\BigData\Driver\Flink\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SqlGatewayClient
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

    public function openSession(array $properties = []): string
    {
        $payload = $this->json('POST', '/v1/sessions', [
            // Flink models properties as a JSON object; an empty PHP array would encode as [].
            'json' => ['properties' => $properties === [] ? new \stdClass() : $properties],
        ]);

        return $this->handle($payload, 'sessionHandle');
    }

    public function closeSession(string $sessionHandle): void
    {
        $this->request('DELETE', '/v1/sessions/' . rawurlencode($sessionHandle));
    }

    public function executeStatement(string $sessionHandle, string $sql): string
    {
        $payload = $this->json(
            'POST',
            '/v1/sessions/' . rawurlencode($sessionHandle) . '/statements',
            ['json' => ['statement' => $sql]],
        );

        return $this->handle($payload, 'operationHandle');
    }

    public function fetchResults(string $sessionHandle, string $operationHandle, int $token = 0): array
    {
        $payload = $this->json('GET', sprintf(
            '/v1/sessions/%s/operations/%s/result/%d',
            rawurlencode($sessionHandle),
            rawurlencode($operationHandle),
            $token,
        ));

        $results = $payload['results'] ?? [];

        $rows = [];
        foreach ($results['data'] ?? [] as $row) {
            $rows[] = $row['fields'] ?? [];
        }

        $end = ($results['resultType'] ?? 'EOS') === 'EOS';

        return [
            'columns' => $results['columns'] ?? [],
            'rows' => $rows,
            'nextToken' => $end ? null : $token + 1,
            'end' => $end,
        ];
    }

    public function cancelOperation(string $sessionHandle, string $operationHandle): void
    {
        $this->request('DELETE', sprintf(
            '/v1/sessions/%s/operations/%s',
            rawurlencode($sessionHandle),
            rawurlencode($operationHandle),
        ));
    }

    private function handle(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RequestException(sprintf('Flink response is missing "%s".', $key));
        }

        return $value;
    }

    private function json(string $method, string $path, array $options = []): array
    {
        $uri = $this->uri($method, $path);

        return Http::json($method, $uri, $this->options($options));
    }

    private function request(string $method, string $path): void
    {
        Http::request($method, $this->uri($method, $path), $this->options());
    }

    private function uri(string $method, string $path): string
    {
        $uri = $this->config->baseUrl . $path;
        $this->logger->debug('Flink request', ['method' => $method, 'uri' => $uri]);

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
        if ($this->config->token === null || $this->config->token === '') {
            return [];
        }

        return ['Authorization' => 'Bearer ' . $this->config->token];
    }
}
