<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use RuntimeException;
use Stripe\HttpClient\ClientInterface;

final class FakeStripeHttpClient implements ClientInterface
{
    private const NOT_FOUND = 404;

    private const OK = 200;

    /** @var array<string, array{body: array<string, mixed>, status: int}> */
    private array $responses = [];

    /** @var list<array{method: string, path: string, params: array<string, mixed>}> */
    public array $requests = [];

    /**
     * @param  array<string, mixed>  $body
     */
    public function answering(string $method, string $path, array $body, int $status = self::OK): self
    {
        $this->responses[strtoupper($method).' '.$path] = ['body' => $body, 'status' => $status];

        return $this;
    }

    public function missing(string $method, string $path): self
    {
        return $this->answering($method, $path, [
            'error' => ['type' => 'invalid_request_error', 'message' => 'No such resource'],
        ], self::NOT_FOUND);
    }

    /**
     * @return list<string>
     */
    public function requestedRoutes(): array
    {
        return array_map(
            static fn (array $request): string => $request['method'].' '.$request['path'],
            $this->requests,
        );
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $route = strtoupper($method).' '.(string) parse_url($absUrl, PHP_URL_PATH);

        $this->requests[] = [
            'method' => strtoupper($method),
            'path' => (string) parse_url($absUrl, PHP_URL_PATH),
            'params' => is_array($params) ? $params : [],
        ];

        $response = $this->responses[$route]
            ?? throw new RuntimeException("FakeStripeHttpClient has no answer for [{$route}].");

        return [json_encode($response['body'], JSON_THROW_ON_ERROR), $response['status'], []];
    }
}
