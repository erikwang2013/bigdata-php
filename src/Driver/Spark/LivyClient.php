<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Spark;

use Erikwang2013\BigData\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LivyClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private LivyConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function createSession(string $kind = 'sql', array $conf = []): array
    {
        // Cast so an empty conf is serialized as {} rather than [], which Livy's map deserializer expects.
        return $this->call('POST', '/sessions', ['kind' => $kind, 'conf' => (object) $conf]);
    }

    public function session(int $id): array
    {
        return $this->call('GET', '/sessions/' . $id);
    }

    public function deleteSession(int $id): void
    {
        $this->send('DELETE', '/sessions/' . $id);
    }

    public function runStatement(int $sessionId, string $code): array
    {
        return $this->call('POST', sprintf('/sessions/%d/statements', $sessionId), ['code' => $code]);
    }

    public function statement(int $sessionId, int $statementId): array
    {
        return $this->call('GET', sprintf('/sessions/%d/statements/%d', $sessionId, $statementId));
    }

    public function batches(int $from = 0, int $size = 20): array
    {
        return $this->call('GET', '/batches?' . http_build_query(['from' => $from, 'size' => $size]));
    }

    public function createBatch(array $batch): array
    {
        return $this->call('POST', '/batches', $batch);
    }

    public function batch(int $id): array
    {
        return $this->call('GET', '/batches/' . $id);
    }

    public function deleteBatch(int $id): void
    {
        $this->send('DELETE', '/batches/' . $id);
    }

    private function call(string $method, string $path, ?array $payload = null): array
    {
        $uri = $this->config->baseUri() . $path;
        $this->logger->debug('Livy request', ['method' => $method, 'uri' => $uri]);

        return Http::json($method, $uri, $this->options($payload));
    }

    private function send(string $method, string $path): void
    {
        $uri = $this->config->baseUri() . $path;
        $this->logger->debug('Livy request', ['method' => $method, 'uri' => $uri]);

        Http::request($method, $uri, $this->options());
    }

    private function options(?array $payload = null): array
    {
        $options = [
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
            'headers' => $this->headers(),
        ];

        if ($payload !== null) {
            $options['body'] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        return $options;
    }

    private function headers(): array
    {
        $headers = [
            // Livy rejects requests without this header (CSRF protection).
            'X-Requested-By' => 'php',
            'Content-Type' => 'application/json',
        ];

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
