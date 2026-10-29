<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Trino;

use Erikwang2013\BigData\Driver\Trino\Exception\RequestException;
use Erikwang2013\BigData\Driver\Trino\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class TrinoClient
{
    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private TrinoConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function query(string $sql): ResultSet
    {
        $uri = $this->config->baseUri() . '/v1/statement';

        try {
            $this->logger->debug('Trino request', ['method' => 'POST', 'uri' => $uri]);
            $payload = Http::json('POST', $uri, $this->options([
                'body' => $sql,
                'headers' => ['Content-Type' => 'text/plain'],
            ]));

            $columns = [];
            $rows = [];
            $raw = [];

            while (true) {
                if (isset($payload['error']['message'])) {
                    throw new RequestException((string) $payload['error']['message']);
                }

                if (!empty($payload['columns'])) {
                    $columns = $payload['columns'];
                }
                foreach ($payload['data'] ?? [] as $row) {
                    $rows[] = $row;
                }
                $raw[] = $payload;

                $nextUri = $payload['nextUri'] ?? null;
                if ($nextUri === null) {
                    break;
                }

                $this->logger->debug('Trino request', ['method' => 'GET', 'uri' => $nextUri]);
                $payload = Http::json('GET', $nextUri, $this->options());
            }

            return new ResultSet($columns, $rows, $raw);
        } catch (RequestException $e) {
            $this->logger->error('Trino query failed: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function cancel(string $nextUri): void
    {
        Http::request('DELETE', $nextUri, $this->options());
    }

    public function catalogs(): array
    {
        return $this->firstColumn('SHOW CATALOGS');
    }

    public function schemas(string $catalog): array
    {
        return $this->firstColumn('SHOW SCHEMAS FROM ' . $this->quoteIdentifier($catalog));
    }

    public function tables(string $catalog, string $schema): array
    {
        return $this->firstColumn(sprintf(
            'SHOW TABLES FROM %s.%s',
            $this->quoteIdentifier($catalog),
            $this->quoteIdentifier($schema),
        ));
    }

    public function columns(string $catalog, string $schema, string $table): array
    {
        return $this->firstColumn(sprintf(
            'SHOW COLUMNS FROM %s.%s.%s',
            $this->quoteIdentifier($catalog),
            $this->quoteIdentifier($schema),
            $this->quoteIdentifier($table),
        ));
    }

    private function firstColumn(string $sql): array
    {
        $values = [];
        foreach ($this->query($sql)->rows() as $row) {
            $values[] = $row[0] ?? null;
        }

        return $values;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
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
        $headers = ['X-Trino-User' => $this->config->user];

        if ($this->config->catalog !== null && $this->config->catalog !== '') {
            $headers['X-Trino-Catalog'] = $this->config->catalog;
        }
        if ($this->config->schema !== null && $this->config->schema !== '') {
            $headers['X-Trino-Schema'] = $this->config->schema;
        }

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
