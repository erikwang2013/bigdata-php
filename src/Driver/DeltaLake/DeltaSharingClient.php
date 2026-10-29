<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\DeltaLake;

use Erikwang2013\BigData\Exception\RequestException;
use Erikwang2013\BigData\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DeltaSharingClient
{
    private const QUERY_OPTIONS = [
        'predicateHints',
        'limit',
        'version',
        'timestamp',
        'startingVersion',
        'endingVersion',
    ];

    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private DeltaSharingConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function shares(): array
    {
        return $this->items('/shares');
    }

    public function schemas(string $share): array
    {
        return $this->items(sprintf('/shares/%s/schemas', rawurlencode($share)));
    }

    public function tables(string $share, string $schema): array
    {
        return $this->items(sprintf(
            '/shares/%s/schemas/%s/tables',
            rawurlencode($share),
            rawurlencode($schema),
        ));
    }

    public function tableMetadata(string $share, string $schema, string $table): array
    {
        $body = (string) $this->request(
            'GET',
            $this->tablePath($share, $schema, $table) . '/metadata',
        )->getBody();
        $metadata = $this->parseNdjson($body)->metadata();

        if ($metadata === []) {
            throw new RequestException('Delta Sharing table metadata response is missing "metaData".', null, $body);
        }

        return $metadata;
    }

    public function query(string $share, string $schema, string $table, array $options = []): DeltaSharingResult
    {
        $payload = [];
        foreach (self::QUERY_OPTIONS as $key) {
            if (array_key_exists($key, $options)) {
                // The protocol calls this field "limitHint"; the public option follows the brief.
                $payload[$key === 'limit' ? 'limitHint' : $key] = $options[$key];
            }
        }

        $response = $this->request('POST', $this->tablePath($share, $schema, $table) . '/query', [
            'json' => $payload === [] ? new \stdClass() : $payload,
        ]);

        return $this->parseNdjson((string) $response->getBody());
    }

    private function items(string $path): array
    {
        $payload = $this->json('GET', $path);
        $items = $payload['items'] ?? [];

        if (!is_array($items)) {
            throw new RequestException(
                'Delta Sharing list response is missing an "items" array.',
                null,
                json_encode($payload) ?: '',
            );
        }

        return $items;
    }

    private function parseNdjson(string $body): DeltaSharingResult
    {
        $protocol = [];
        $metadata = [];
        $files = [];

        foreach (preg_split('/\r\n|\n|\r/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new RequestException('Invalid NDJSON response line: ' . $e->getMessage(), null, $line);
            }

            if (!is_array($record)) {
                throw new RequestException('Invalid NDJSON response line: expected a JSON object.', null, $line);
            }

            foreach (['protocol', 'metaData', 'file'] as $type) {
                if (array_key_exists($type, $record) && !is_array($record[$type])) {
                    throw new RequestException(
                        sprintf('Invalid NDJSON "%s" line: expected an object.', $type),
                        null,
                        $line,
                    );
                }
            }

            if (isset($record['endStreamAction']['errorMessage'])) {
                throw new RequestException(
                    'Delta Sharing stream error: ' . (string) $record['endStreamAction']['errorMessage'],
                    null,
                    $line,
                );
            }

            if (isset($record['protocol'])) {
                $protocol = $record['protocol'];
            }
            if (isset($record['metaData'])) {
                $metadata = $record['metaData'];
            }
            if (isset($record['file'])) {
                $files[] = $record['file'];
            }
        }

        return new DeltaSharingResult($protocol, $metadata, $files);
    }

    private function tablePath(string $share, string $schema, string $table): string
    {
        return sprintf(
            '/shares/%s/schemas/%s/tables/%s',
            rawurlencode($share),
            rawurlencode($schema),
            rawurlencode($table),
        );
    }

    private function json(string $method, string $path, array $extra = []): array
    {
        $uri = $this->config->endpoint . $path;
        $this->logger->debug('Delta Sharing request', ['method' => $method, 'uri' => $uri]);

        return Http::json($method, $uri, $this->options($extra));
    }

    private function request(string $method, string $path, array $extra = []): ResponseInterface
    {
        $uri = $this->config->endpoint . $path;
        $this->logger->debug('Delta Sharing request', ['method' => $method, 'uri' => $uri]);

        return Http::request($method, $uri, $this->options($extra));
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
        return ['Authorization' => 'Bearer ' . $this->config->token];
    }
}
