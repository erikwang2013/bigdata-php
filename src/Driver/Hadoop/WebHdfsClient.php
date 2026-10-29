<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Hadoop;

use Erikwang2013\BigData\Driver\Hadoop\Exception\RequestException;
use Erikwang2013\BigData\Driver\Hadoop\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class WebHdfsClient
{
    private const MAX_REDIRECTS = 5;

    private ClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(
        private WebHdfsConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function listStatus(string $path): array
    {
        $data = $this->json('GET', $this->uri($path, ['op' => 'LISTSTATUS']));

        return $data['FileStatuses']['FileStatus'] ?? [];
    }

    public function fileStatus(string $path): array
    {
        $data = $this->json('GET', $this->uri($path, ['op' => 'GETFILESTATUS']));

        return $data['FileStatus'] ?? [];
    }

    public function mkdirs(string $path): bool
    {
        return $this->boolean('PUT', $this->uri($path, ['op' => 'MKDIRS']));
    }

    public function delete(string $path, bool $recursive = false): bool
    {
        return $this->boolean('DELETE', $this->uri($path, [
            'op' => 'DELETE',
            'recursive' => $recursive ? 'true' : 'false',
        ]));
    }

    public function rename(string $from, string $to): bool
    {
        return $this->boolean('PUT', $this->uri($from, [
            'op' => 'RENAME',
            'destination' => $to,
        ]));
    }

    public function read(string $path): string
    {
        return (string) $this->follow('GET', $this->uri($path, ['op' => 'OPEN']))->getBody();
    }

    public function readStream(string $path): StreamInterface
    {
        return $this->follow('GET', $this->uri($path, ['op' => 'OPEN']))->getBody();
    }

    public function write(string $path, string $contents, bool $overwrite = false): bool
    {
        $this->follow('PUT', $this->uri($path, [
            'op' => 'CREATE',
            'overwrite' => $overwrite ? 'true' : 'false',
        ]), [
            'body' => $contents,
            'headers' => ['Content-Type' => 'application/octet-stream'],
        ]);

        return true;
    }

    private function boolean(string $method, string $uri): bool
    {
        $data = $this->json($method, $uri);

        return (bool) ($data['boolean'] ?? false);
    }

    private function json(string $method, string $uri): array
    {
        $this->logRequest($method, $uri);

        try {
            return Http::json($method, $uri, $this->options());
        } catch (RequestException $e) {
            throw $this->mapRemoteException($e);
        }
    }

    /**
     * WebHDFS answers reads and writes with a 307 to a DataNode. Follow it
     * manually so the request can be re-sent without the caller's auth headers;
     * the DataNode only accepts user.name/delegation query parameters.
     */
    private function follow(string $method, string $uri, array $options = []): ResponseInterface
    {
        $response = $this->send($method, $uri, $options);

        for ($redirects = 0; $this->isRedirect($response); $redirects++) {
            if ($redirects >= self::MAX_REDIRECTS) {
                throw new RequestException('Too many redirects (max ' . self::MAX_REDIRECTS . ').');
            }

            $location = $response->getHeaderLine('Location');
            if ($location === '') {
                throw new RequestException(
                    sprintf('HTTP %d redirect without a Location header.', $response->getStatusCode()),
                );
            }

            // The DataNode is a different host: drop both default headers and
            // client-level auth, which Guzzle would otherwise re-apply.
            $response = $this->send(
                $method,
                $this->redirectUri($location),
                array_merge($options, ['headers' => null, 'auth' => null]),
            );
        }

        return $response;
    }

    private function send(string $method, string $uri, array $options = []): ResponseInterface
    {
        $this->logRequest($method, $uri);

        try {
            return Http::request($method, $uri, $this->options($options));
        } catch (RequestException $e) {
            throw $this->mapRemoteException($e);
        }
    }

    private function logRequest(string $method, string $uri): void
    {
        // Query parameters carry user.name and delegation tokens; never log them.
        $this->logger->debug('WebHDFS request', [
            'method' => $method,
            'uri' => (string) (new Uri($uri))->withQuery(''),
        ]);
    }

    private function isRedirect(ResponseInterface $response): bool
    {
        return in_array($response->getStatusCode(), [307, 308], true);
    }

    private function redirectUri(string $location): string
    {
        $uri = new Uri($location);
        $query = Query::parse($uri->getQuery());
        $query['user.name'] = $this->config->user;

        if ($this->config->delegationToken !== null && $this->config->delegationToken !== '') {
            $query['delegation'] = $this->config->delegationToken;
        }

        return (string) $uri->withQuery(Query::build($query));
    }

    private function mapRemoteException(RequestException $e): RequestException
    {
        $decoded = json_decode($e->body, true);
        $message = $decoded['RemoteException']['message'] ?? null;

        return is_string($message)
            ? new RequestException($message, $e->statusCode, $e->body)
            : $e;
    }

    private function uri(string $path, array $params): string
    {
        $params['user.name'] = $this->config->user;

        if ($this->config->delegationToken !== null && $this->config->delegationToken !== '') {
            $params['delegation'] = $this->config->delegationToken;
        }

        $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));

        return $this->config->baseUri()
            . '/' . implode('/', $segments)
            . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function options(array $extra = []): array
    {
        return array_merge([
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
            // Follow WebHDFS redirects ourselves so auth headers stay on the NameNode.
            'allow_redirects' => false,
        ], $extra);
    }
}
