<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Oozie;

use Erikwang2013\BigData\Exception\RequestException;
use Erikwang2013\BigData\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class OozieClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private OozieConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function submitWorkflow(string $xml): string
    {
        return $this->submit($xml);
    }

    public function submitCoordinator(string $xml): string
    {
        return $this->submit($xml);
    }

    public function submitBundle(string $xml): string
    {
        return $this->submit($xml);
    }

    public function job(string $jobId): array
    {
        $uri = $this->uri($this->jobPath($jobId) . '?show=info');
        $this->logger->debug('Oozie request', ['method' => 'GET', 'uri' => $uri]);

        $data = Http::json('GET', $uri, $this->options());

        if (isset($data['error'])) {
            throw new RequestException((string) ($data['message'] ?? $data['error']));
        }

        return $data;
    }

    public function status(string $jobId): string
    {
        $body = $this->text($this->jobPath($jobId) . '?show=status');

        $data = json_decode($body, true);
        if (is_array($data) && isset($data['status']) && is_string($data['status']) && $data['status'] !== '') {
            return $data['status'];
        }

        // Older Oozie proxies answer with a plain-text status.
        return trim($body);
    }

    public function log(string $jobId): string
    {
        return $this->text($this->jobPath($jobId) . '?show=log');
    }

    public function start(string $jobId): void
    {
        $this->action($jobId, 'start');
    }

    public function rerun(string $jobId, array $properties = []): void
    {
        $this->request('PUT', $this->jobPath($jobId) . '?action=rerun', [
            'body' => $this->configurationXml($properties),
            'headers' => ['Content-Type' => 'application/xml'],
        ]);
    }

    public function suspend(string $jobId): void
    {
        $this->action($jobId, 'suspend');
    }

    public function resume(string $jobId): void
    {
        $this->action($jobId, 'resume');
    }

    public function kill(string $jobId): void
    {
        $this->action($jobId, 'kill');
    }

    private function submit(string $xml): string
    {
        $body = (string) $this->request('POST', '/v2/jobs', [
            'body' => $xml,
            'headers' => ['Content-Type' => 'application/xml'],
        ])->getBody();

        $data = json_decode($body, true);
        if (is_array($data) && isset($data['id']) && is_string($data['id']) && $data['id'] !== '') {
            return $data['id'];
        }

        // Older Oozie proxies answer with the bare job id as plain text.
        return trim($body);
    }

    private function action(string $jobId, string $action): void
    {
        $this->request('PUT', $this->jobPath($jobId) . '?action=' . $action);
    }

    private function text(string $path): string
    {
        return (string) $this->request('GET', $path)->getBody();
    }

    private function jobPath(string $jobId): string
    {
        return '/v2/job/' . rawurlencode($jobId);
    }

    private function request(string $method, string $path, array $extra = []): ResponseInterface
    {
        $uri = $this->uri($path);

        $this->logger->debug('Oozie request', ['method' => $method, 'uri' => $uri]);

        return Http::request($method, $uri, $this->options($extra));
    }

    private function configurationXml(array $properties): string
    {
        $xml = '<configuration>';
        foreach ($properties as $name => $value) {
            $xml .= sprintf(
                '<property><name>%s</name><value>%s</value></property>',
                $this->escapeXml((string) $name),
                $this->escapeXml((string) $value),
            );
        }

        return $xml . '</configuration>';
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function uri(string $path): string
    {
        return rtrim($this->config->baseUrl, '/') . $path;
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
        if ($this->config->username !== null && $this->config->username !== '') {
            return ['Authorization' => 'Basic ' . base64_encode(
                $this->config->username . ':' . ($this->config->password ?? ''),
            )];
        }

        return [];
    }
}
