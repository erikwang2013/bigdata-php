<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\HBase;

use Erikwang2013\BigData\Driver\HBase\Exception\RequestException;
use Erikwang2013\BigData\Driver\HBase\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class HBaseClient
{
    private readonly ClientInterface $http;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HBaseConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    /** @return list<string> */
    public function tables(): array
    {
        $payload = $this->json('GET', $this->url('/'));
        $tables = $payload['Table'] ?? $payload['table'] ?? $payload['tables'] ?? [];

        $names = [];
        foreach ($tables as $table) {
            $names[] = is_array($table) ? (string) ($table['name'] ?? '') : (string) $table;
        }

        return $names;
    }

    /** @param list<string> $columns Column family names. */
    public function createTable(string $table, array $columns): void
    {
        $columnSchemas = [];
        foreach ($columns as $column) {
            $columnSchemas[] = ['name' => (string) $column];
        }

        $this->request('POST', $this->url($this->path($table, 'schema')), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['name' => $table, 'ColumnSchema' => $columnSchemas], JSON_THROW_ON_ERROR),
        ]);
    }

    public function deleteTable(string $table): void
    {
        $this->request('DELETE', $this->url($this->path($table, 'schema')));
    }

    /**
     * @param list<string> $columns Optional "family:qualifier" columns to fetch.
     * @return array<string, string> Decoded column => value for the row.
     */
    public function get(string $table, string $row, array $columns = []): array
    {
        $path = $this->rowPath($table, $row);
        if ($columns !== []) {
            $path .= '/' . implode(',', array_map(
                static fn (string $column): string => rawurlencode(base64_encode($column)),
                $columns,
            ));
        }

        return $this->parseRows($this->json('GET', $this->url($path)))[0]['cells'] ?? [];
    }

    /** @param array<string, string> $cells Decoded column => value. */
    public function put(string $table, string $row, array $cells): void
    {
        $encodedCells = [];
        foreach ($cells as $column => $value) {
            $encodedCells[] = [
                'column' => base64_encode((string) $column),
                '$' => base64_encode((string) $value),
            ];
        }

        $this->request('PUT', $this->url($this->rowPath($table, $row)), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['Row' => [[
                'key' => base64_encode($row),
                'Cell' => $encodedCells,
            ]]], JSON_THROW_ON_ERROR),
        ]);
    }

    public function delete(string $table, string $row): void
    {
        $this->request('DELETE', $this->url($this->rowPath($table, $row)));
    }

    /**
     * Creates a scanner, fetches one batch and deletes the scanner.
     *
     * @param array{startRow?: string, endRow?: string, limit?: int} $options
     * @return list<array{key: string, cells: array<string, string>}>
     */
    public function scan(string $table, array $options = []): array
    {
        $scanner = $this->scannerCreate($table, $options);

        try {
            return $this->scannerGet($scanner, (int) ($options['limit'] ?? 100));
        } finally {
            $this->scannerDelete($scanner);
        }
    }

    /**
     * @param array{startRow?: string, endRow?: string} $options
     * @return string Scanner path (e.g. "/users/scanner/42") for scannerGet()/scannerDelete().
     */
    public function scannerCreate(string $table, array $options = []): string
    {
        $body = [];
        if (isset($options['startRow'])) {
            $body['startRow'] = base64_encode((string) $options['startRow']);
        }
        if (isset($options['endRow'])) {
            $body['endRow'] = base64_encode((string) $options['endRow']);
        }

        $response = $this->request('POST', $this->url($this->path($table, 'scanner')), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode((object) $body, JSON_THROW_ON_ERROR),
        ]);

        $location = $response->getHeaderLine('Location');
        if ($location !== '') {
            return self::locationPath($location);
        }

        $reply = trim((string) $response->getBody());
        if ($reply === '') {
            throw new RequestException('HBase scanner creation returned no scanner id.');
        }

        $decoded = json_decode($reply, true);
        $id = is_array($decoded) ? ($decoded['scannerId'] ?? $decoded['id'] ?? null) : null;
        $id = is_string($id) && $id !== '' ? $id : $reply;

        return str_starts_with($id, '/') ? $id : $this->path($table, 'scanner', $id);
    }

    /** @return list<array{key: string, cells: array<string, string>}> */
    public function scannerGet(string $scannerId, int $count = 100): array
    {
        $response = $this->request('GET', $this->scannerUrl($scannerId) . '?count=' . $count);

        // HBase answers 204 No Content once the scanner is exhausted.
        if ($response->getStatusCode() === 204) {
            return [];
        }

        return $this->parseRows(Http::decodeJson((string) $response->getBody()));
    }

    public function scannerDelete(string $scannerId): void
    {
        $this->request('DELETE', $this->scannerUrl($scannerId));
    }

    private function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $this->logger->debug('HBase request', ['method' => $method, 'uri' => $uri]);

        return Http::request($method, $uri, $this->options($options));
    }

    private function json(string $method, string $uri, array $options = []): array
    {
        $this->logger->debug('HBase request', ['method' => $method, 'uri' => $uri]);

        return Http::json($method, $uri, $this->options($options));
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

        if ($this->config->username !== null && $this->config->username !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode(
                $this->config->username . ':' . ($this->config->password ?? ''),
            );
        }

        return $headers;
    }

    private function url(string $path): string
    {
        return $this->config->baseUrl . '/' . ltrim($path, '/');
    }

    private function scannerUrl(string $scannerId): string
    {
        if (str_starts_with($scannerId, 'http://') || str_starts_with($scannerId, 'https://')) {
            return $scannerId;
        }

        return $this->url($scannerId);
    }

    private function locationPath(string $location): string
    {
        $path = parse_url($location, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $location;
    }

    private function rowPath(string $table, string $row): string
    {
        return $this->path($table, base64_encode($row));
    }

    private function path(string ...$segments): string
    {
        return '/' . implode('/', array_map('rawurlencode', $segments));
    }

    /** @return list<array{key: string, cells: array<string, string>}> */
    private function parseRows(array $payload): array
    {
        $rows = [];
        foreach ($payload['Row'] ?? [] as $row) {
            $cells = [];
            foreach ($row['Cell'] ?? [] as $cell) {
                $cells[self::decode((string) ($cell['column'] ?? ''))]
                    = self::decode((string) ($cell['$'] ?? ''));
            }

            $rows[] = [
                'key' => self::decode((string) ($row['key'] ?? '')),
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    private static function decode(string $value): string
    {
        $decoded = base64_decode($value, true);

        return $decoded === false ? '' : $decoded;
    }
}
