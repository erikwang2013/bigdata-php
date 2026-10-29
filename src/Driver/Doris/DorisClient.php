<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Doris;

use Erikwang2013\BigData\Driver\Doris\Exception\ConnectionException;
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

    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private DorisConfig $config,
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
        try {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($params);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            return new ResultSet($rows === [] ? [] : array_keys($rows[0]), $rows, $rows);
        } catch (\PDOException $e) {
            throw $this->mapPdoException($e);
        }
    }

    public function execute(string $sql, array $params = []): int
    {
        try {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($params);

            return $statement->rowCount();
        } catch (\PDOException $e) {
            throw $this->mapPdoException($e);
        }
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

    private function mapPdoException(\PDOException $e): \Throwable
    {
        // SQLSTATE class 08 is "connection exception"; MySQL may also report
        // network failures as HY000 with a connection-related message.
        $state = is_string($e->getCode()) ? $e->getCode() : (string) ($e->errorInfo[0] ?? '');
        $message = $e->getMessage();

        if (str_starts_with($state, '08')
            || stripos($message, 'connection refused') !== false
            || stripos($message, 'unknown host') !== false
            || stripos($message, 'getaddrinfo') !== false
        ) {
            return new ConnectionException($message, 0, $e);
        }

        return new RequestException($message, null, '', $e);
    }

    private function pdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            return $this->pdo = new \PDO(
                $this->config->dsn(),
                $this->config->username,
                $this->config->password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
            );
        } catch (\PDOException $e) {
            $message = str_contains($e->getMessage(), 'could not find driver')
                ? 'Doris SQL requires ext-pdo_mysql (see composer.json "suggest"). Install the pdo_mysql extension. streamLoad() works over HTTP without PDO.'
                : 'Doris PDO connection failed: ' . $e->getMessage() . ' streamLoad() works over HTTP without PDO.';

            throw new ConnectionException($message, 0, $e);
        }
    }
}
