<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response as Status;
use Throwable;

final class GoogleCalendarApi
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    private const CONNECT_TIMEOUT_SECONDS = 2;

    public function __construct(
        private readonly GoogleAccessTokens $tokens,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws CalendarAuthorizationRevoked
     */
    public function forConnection(string $connectionId, string $method, string $path, array $options = []): Response
    {
        $response = $this->send($this->tokens->current($connectionId), $method, $path, $options);

        if ($response->status() !== Status::HTTP_UNAUTHORIZED) {
            return $response;
        }

        $response = $this->send($this->tokens->refreshed($connectionId), $method, $path, $options);

        if ($response->status() === Status::HTTP_UNAUTHORIZED) {
            throw CalendarAuthorizationRevoked::forConnection($connectionId);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withAccessToken(#[SensitiveParameter] string $accessToken, string $method, string $path, array $options = []): Response
    {
        return $this->send($accessToken, $method, $path, $options);
    }

    public static function calendarPath(string $externalCalendarId): string
    {
        return '/calendars/'.rawurlencode($externalCalendarId);
    }

    public static function eventPath(string $externalCalendarId, string $externalEventId): string
    {
        return self::calendarPath($externalCalendarId).'/events/'.rawurlencode($externalEventId);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function send(#[SensitiveParameter] string $accessToken, string $method, string $path, array $options): Response
    {
        try {
            return Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout($this->timeoutSeconds)
                ->send($method, self::BASE_URL.$path, $options);
        } catch (Throwable $failure) {
            throw GoogleApiFailure::unreachable($method, $path, $failure::class);
        }
    }
}
