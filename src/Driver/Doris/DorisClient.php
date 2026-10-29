<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Doris;

use Erikwang2013\BigData\Driver\Doris\Exception\DorisException;
use Erikwang2013\BigData\Driver\Doris\Exception\RequestException;
use Erikwang2013\BigData\Driver\Doris\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DorisClient
{
    private ?\PDO $pdo;

    private readonly ClientInterface $http;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly DorisConfig $config,
        ?\PDO $pdo = null,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->pdo = $pdo;
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function query(string $sql, array $params = []): ResultSet
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return new ResultSet($rows === [] ? [] : array_keys($rows[0]), $rows, $rows);
    }

    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    public function streamLoad(string $table, string $data, array $options = []): array
    {
        $uri = sprintf(
            '%s://%s:%d/api/%s/%s/_stream_load',
            $this->config->scheme,
            $this->config->host,
            $this->config->httpPort,
            $this->config->database,
            $table,
        );

        // Stream Load options (label, format, max_filter_ratio, ...) are headers on the wire.
        $headers = array_merge([
            'label' => 'php_' . uniqid(),
            'format' => 'json',
            'Expect' => '100-continue',
        ], $options);

        try {
            $this->logger->debug('Doris stream load', ['method' => 'PUT', 'uri' => $uri]);
            $response = Http::json('PUT', $uri, [
                'client' => $this->http,
                'auth' => [$this->config->username, $this->config->password],
                'headers' => $headers,
                'body' => $data,
                'timeout' => $this->config->timeout,
                'verify' => $this->config->verifySsl,
            ]);

            if (($response['Status'] ?? null) !== 'Success') {
                throw new RequestException((string) ($response['Message'] ?? 'Stream load failed'));
            }
        } catch (DorisException $e) {
            $this->logger->error('Doris stream load failed: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }

        return $response;
    }

    private function pdo(): \PDO
    {
        return $this->pdo ??= new \PDO(
            $this->config->dsn(),
            $this->config->username,
            $this->config->password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
    }
}
