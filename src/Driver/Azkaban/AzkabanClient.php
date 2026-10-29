<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Azkaban;

use Erikwang2013\BigData\Exception\RequestException;
use Erikwang2013\BigData\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class AzkabanClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    private ?string $sessionId = null;

    public function __construct(
        private AzkabanConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function login(): string
    {
        if ($this->sessionId !== null) {
            return $this->sessionId;
        }

        $payload = $this->decode('POST', $this->config->baseUrl . '/manager?action=login', [
            'form_params' => [
                'action' => 'login',
                'username' => $this->config->username,
                'password' => $this->config->password,
            ],
        ]);

        $sessionId = $payload['session.id'] ?? null;
        if (!is_string($sessionId) || $sessionId === '') {
            throw new RequestException('Azkaban login response does not contain "session.id".');
        }

        return $this->sessionId = $sessionId;
    }

    public function projects(): array
    {
        return $this->decode('GET', $this->url('/index', ['ajax' => 'fetchuserprojects']));
    }

    public function flows(string $project): array
    {
        return $this->decode('GET', $this->url('/manager', [
            'ajax' => 'fetchprojectflows',
            'project' => $project,
        ]));
    }

    public function executeFlow(string $project, string $flow, array $options = []): array
    {
        return $this->decode('POST', $this->url('/executor', ['ajax' => 'executeFlow']), [
            'form_params' => array_merge(['project' => $project, 'flow' => $flow], $options),
        ]);
    }

    public function execution(string $execId): array
    {
        return $this->decode('GET', $this->url('/executor', [
            'ajax' => 'fetchexecflow',
            'execid' => $execId,
        ]));
    }

    public function executionLog(string $execId, int $offset = 0, int $length = 50000): string
    {
        $payload = $this->decode('GET', $this->url('/executor', [
            'ajax' => 'fetchExecFlowLogs',
            'execid' => $execId,
            'offset' => $offset,
            'length' => $length,
        ]));

        $data = $payload['data'] ?? null;
        if (!is_string($data)) {
            throw new RequestException(
                'Azkaban execution log response does not contain "data".',
                null,
                (string) json_encode($payload),
            );
        }

        return $data;
    }

    public function cancel(string $execId): void
    {
        $this->decode('GET', $this->url('/executor', [
            'ajax' => 'cancelFlow',
            'execid' => $execId,
        ]));
    }

    private function url(string $path, array $query): string
    {
        return $this->config->baseUrl . $path . '?' . http_build_query(
            array_merge($query, ['session.id' => $this->login()]),
        );
    }

    private function log(string $method, string $uri): void
    {
        $this->logger->debug('Azkaban request', [
            'method' => $method,
            'uri' => preg_replace('/session\.id=[^&]*/', 'session.id=***', $uri),
        ]);
    }

    private function decode(string $method, string $uri, array $options = []): array
    {
        $this->log($method, $uri);

        $payload = Http::json($method, $uri, $this->options($options));

        if (isset($payload['error'])) {
            throw new RequestException(
                is_string($payload['error']) ? $payload['error'] : (string) json_encode($payload['error']),
                null,
                (string) json_encode($payload),
            );
        }

        return $payload;
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
