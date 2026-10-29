<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Support;

use Erikwang2013\BigData\Exception\ConnectionException;
use Erikwang2013\BigData\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

final class Http
{
    /**
     * A Guzzle client may be injected through the "client" option so drivers
     * and tests can supply their own handler without global state.
     */
    public static function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $response = self::raw($method, $uri, $options);
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            $body = (string) $response->getBody();
            throw new RequestException(sprintf('HTTP %d: %s', $status, $body), $status, $body);
        }

        return $response;
    }

    /**
     * Send a request without treating a non-2xx status as an error. Used by
     * drivers that must inspect 3xx redirects manually (WebHDFS).
     */
    public static function raw(string $method, string $uri, array $options = []): ResponseInterface
    {
        $client = $options['client'] ?? new Client();
        unset($options['client']);
        $options['http_errors'] = false;

        try {
            return $client->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }
    }

    public static function json(string $method, string $uri, array $options = []): array
    {
        return self::decodeJson((string) self::request($method, $uri, $options)->getBody());
    }

    public static function decodeJson(string $body): array
    {
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
