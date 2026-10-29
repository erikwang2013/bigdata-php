<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Paimon\Support;

use Erikwang2013\BigData\Driver\Paimon\Exception\ConnectionException;
use Erikwang2013\BigData\Driver\Paimon\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

final class Http
{
    /**
     * A Guzzle client may be injected through the "client" option; WebHdfsStorage
     * does this so callers can swap in their own handler (and tests can mock it).
     */
    public static function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $client = $options['client'] ?? new Client();
        unset($options['client']);
        $options['http_errors'] = false;

        try {
            $response = $client->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        // 3xx is followed manually by WebHDFS (allow_redirects=false), so only >= 400 is an error here.
        if ($status >= 400) {
            $body = (string) $response->getBody();
            throw new RequestException(sprintf('HTTP %d: %s', $status, $body), $status, $body);
        }

        return $response;
    }

    public static function json(string $method, string $uri, array $options = []): array
    {
        $body = (string) self::request($method, $uri, $options)->getBody();

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RequestException('Invalid JSON response: ' . $e->getMessage(), null, $body);
        }

        if (!is_array($decoded)) {
            throw new RequestException('Invalid JSON response: expected an object or array.', null, $body);
        }

        return $decoded;
    }
}
