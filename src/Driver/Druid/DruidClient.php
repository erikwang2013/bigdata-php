<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Druid;

use Erikwang2013\BigData\Driver\Druid\Exception\RequestException;
use Erikwang2013\BigData\Driver\Druid\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DruidClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private DruidConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function sql(string $sql, array $context = []): ResultSet
    {
        $payload = $this->requestJson('POST', '/druid/v2/sql', [
            'json' => [
                'query' => $sql,
                'resultFormat' => 'object',
                // Druid expects a JSON object; PHP would encode an empty array as [].
                'context' => $context === [] ? new \stdClass() : $context,
            ],
        ]);

        // PHP 8.0 has no list check; reindexing is a no-op only when keys are 0..n-1.
        if (array_values($payload) !== $payload) {
            throw new RequestException('Invalid Druid SQL response: expected a JSON array of row objects.');
        }

        $columns = $payload === [] ? [] : array_keys($payload[0]);

        return new ResultSet($columns, $payload, $payload);
    }

    public function nativeQuery(array $query): array
    {
        return $this->requestJson('POST', '/druid/v2', ['json' => $query]);
    }

    public function datasources(): array
    {
        return $this->requestJson('GET', '/druid/v2/datasources');
    }

    private function requestJson(string $method, string $path, array $extra = []): array
    {
        $uri = $this->config->baseUri() . $path;
        $this->logger->debug('Druid request', ['method' => $method, 'uri' => $uri]);

        return Http::json($method, $uri, $this->options($extra));
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
        $headers = [];

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
