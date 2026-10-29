<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DolphinScheduler;

use Erikwang2013\BigData\Driver\DolphinScheduler\Exception\RequestException;
use Erikwang2013\BigData\Driver\DolphinScheduler\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DolphinSchedulerClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    private ?string $sessionId = null;

    public function __construct(
        private DolphinSchedulerConfig $config,
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

        $payload = Http::json('POST', $this->uri('POST', '/login'), $this->baseOptions() + [
            'form_params' => [
                'userName' => $this->config->username,
                'userPassword' => $this->config->password,
            ],
        ]);
        $this->assertSuccess($payload);

        $sessionId = $payload['data']['sessionId'] ?? null;
        if (!is_string($sessionId) || $sessionId === '') {
            throw new RequestException('DolphinScheduler login response is missing "data.sessionId".');
        }

        return $this->sessionId = $sessionId;
    }

    public function projects(int $page = 1, int $size = 10): array
    {
        return $this->call('GET', '/projects', ['query' => $this->pagination($page, $size)]);
    }

    public function processDefinitions(int $projectCode, int $page = 1, int $size = 10): array
    {
        return $this->call('GET', "/projects/{$projectCode}/process-definition", [
            'query' => $this->pagination($page, $size),
        ]);
    }

    public function startProcess(int $projectCode, int $processDefinitionCode, array $params = []): array
    {
        return $this->call('POST', "/projects/{$projectCode}/executions", [
            'json' => array_merge($params, ['processDefinitionCode' => $processDefinitionCode]),
        ]);
    }

    public function stopProcess(int $projectCode, int $processInstanceId): void
    {
        $this->call('POST', "/projects/{$projectCode}/executions/{$processInstanceId}/stop");
    }

    public function processInstances(int $projectCode, int $page = 1, int $size = 10, array $filters = []): array
    {
        return $this->call('GET', "/projects/{$projectCode}/process-instances", [
            'query' => array_merge($filters, $this->pagination($page, $size)),
        ]);
    }

    public function taskInstances(int $projectCode, int $processInstanceId, int $page = 1, int $size = 10): array
    {
        return $this->call('GET', "/projects/{$projectCode}/task-instances", [
            'query' => ['processInstanceId' => $processInstanceId] + $this->pagination($page, $size),
        ]);
    }

    public function taskLog(int $taskInstanceId, int $skipLineNum = 0, int $limit = 1000): string
    {
        $response = Http::request('GET', $this->uri('GET', "/log/{$taskInstanceId}"), $this->options([
            'query' => ['skipLineNum' => $skipLineNum, 'limit' => $limit],
        ]));

        return (string) $response->getBody();
    }

    /**
     * Sends a JSON request and returns the "data" member of the response envelope.
     * A scalar "data" (some DolphinScheduler versions return a bare id) is wrapped as
     * ['data' => value] so no information is lost.
     */
    private function call(string $method, string $path, array $extra = []): array
    {
        $payload = Http::json($method, $this->uri($method, $path), $this->options($extra));
        $this->assertSuccess($payload);

        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : ['data' => $data];
    }

    private function assertSuccess(array $payload): void
    {
        $code = $payload['code'] ?? null;
        if ($code !== null && (int) $code === 0) {
            return;
        }

        $message = $payload['msg'] ?? null;
        $body = (string) json_encode($payload);

        throw new RequestException(
            is_string($message) && $message !== '' ? $message : 'DolphinScheduler API error: ' . $body,
            null,
            $body,
        );
    }

    private function uri(string $method, string $path): string
    {
        $uri = $this->config->baseUrl . $path;
        $this->logger->debug('DolphinScheduler request', ['method' => $method, 'uri' => $uri]);

        return $uri;
    }

    private function pagination(int $page, int $size): array
    {
        return ['pageNo' => $page, 'pageSize' => $size];
    }

    private function options(array $extra = []): array
    {
        $options = array_merge($this->baseOptions(), $extra);
        $options['headers'] = array_merge($this->headers(), $extra['headers'] ?? []);

        return $options;
    }

    private function baseOptions(): array
    {
        return [
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
        ];
    }

    private function headers(): array
    {
        if ($this->config->token !== null && $this->config->token !== '') {
            return ['token' => $this->config->token];
        }

        return ['sessionId' => $this->login()];
    }
}
