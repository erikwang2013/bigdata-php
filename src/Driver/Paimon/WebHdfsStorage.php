<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Paimon;

use Erikwang2013\BigData\Driver\Paimon\Exception\RequestException;
use Erikwang2013\BigData\Driver\Paimon\Support\Http;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class WebHdfsStorage implements PaimonStorage
{
    private const MAX_REDIRECTS = 5;

    private readonly ClientInterface $http;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly WebHdfsConfig $config,
        ?ClientInterface $http = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    public function list(string $path): array
    {
        $uri = $this->uri($path, ['op' => 'LISTSTATUS']);
        $this->logger->debug('WebHDFS request', ['method' => 'GET', 'uri' => $uri]);

        $data = Http::json('GET', $uri, $this->options());

        return array_map(
            static fn (array $status): string => (string) ($status['pathSuffix'] ?? ''),
            $data['FileStatuses']['FileStatus'] ?? [],
        );
    }

    public function read(string $path): string
    {
        return (string) $this->follow($this->uri($path, ['op' => 'OPEN']))->getBody();
    }

    /**
     * WebHDFS answers OPEN with a 307 to a DataNode. Follow it manually because
     * the redirect Location is only valid with the user.name query parameter.
     */
    private function follow(string $uri): ResponseInterface
    {
        $response = $this->send($uri);

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

            // The DataNode is a different host: never forward client-level credentials.
            $response = $this->send($this->redirectUri($location), [
                'headers' => null,
                'auth' => null,
            ]);
        }

        return $response;
    }

    private function send(string $uri, array $options = []): ResponseInterface
    {
        $this->logger->debug('WebHDFS request', ['method' => 'GET', 'uri' => $uri]);

        return Http::request('GET', $uri, array_merge($this->options(), $options));
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

        return (string) $uri->withQuery(Query::build($query));
    }

    private function uri(string $path, array $params): string
    {
        $params['user.name'] = $this->config->user;
        $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));

        return $this->config->baseUri()
            . '/' . implode('/', $segments)
            . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function options(): array
    {
        return [
            'client' => $this->http,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifySsl,
            // Follow WebHDFS redirects ourselves so they can carry user.name.
            'allow_redirects' => false,
        ];
    }
}
