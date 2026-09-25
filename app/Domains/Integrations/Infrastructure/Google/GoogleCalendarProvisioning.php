<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Contracts\CalendarProvisioning;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response as Status;
use Throwable;

final class GoogleCalendarProvisioning implements CalendarProvisioning
{
    private const CALENDARS_PATH = '/calendars';

    private const GONE_STATUSES = [Status::HTTP_NOT_FOUND, Status::HTTP_GONE];

    private const UNREACHABLE_CALENDAR_STATUSES = [Status::HTTP_FORBIDDEN, Status::HTTP_NOT_FOUND, Status::HTTP_GONE];

    public function __construct(
        private readonly GoogleCalendarApi $api,
        private readonly GoogleAccessTokens $tokens,
        private readonly int $timeoutSeconds,
    ) {}

    public function createCalendar(CalendarGrant $grant, BusinessCalendarProfile $business): string
    {
        $response = $this->requestWithGrant($grant, 'POST', self::CALENDARS_PATH, [
            'json' => [
                'summary' => $business->calendarName(),
                'timeZone' => $business->timezone,
            ],
        ]);

        $calendarId = $response->successful() ? $response->json('id') : null;

        if (! is_string($calendarId) || $calendarId === '') {
            throw CalendarAuthorizationFailed::calendarNotProvisioned();
        }

        return $calendarId;
    }

    public function adoptCalendar(CalendarGrant $grant, string $externalCalendarId, BusinessCalendarProfile $business): string
    {
        $response = $this->requestWithGrant($grant, 'GET', GoogleCalendarApi::calendarPath($externalCalendarId));

        if ($response->successful()) {
            return $externalCalendarId;
        }

        if (in_array($response->status(), self::UNREACHABLE_CALENDAR_STATUSES, true)) {
            return $this->createCalendar($grant, $business);
        }

        throw CalendarAuthorizationFailed::calendarNotProvisioned();
    }

    public function deleteCalendar(CalendarConnection $connection): void
    {
        $path = GoogleCalendarApi::calendarPath($connection->externalCalendarId());

        try {
            $response = $this->api->forConnection($connection->id, 'DELETE', $path);
        } catch (CalendarAuthorizationRevoked) {
            return;
        }

        self::assertCalendarDeleted($path, $response);
    }

    public function discardCalendar(CalendarGrant $grant, string $externalCalendarId): void
    {
        $path = GoogleCalendarApi::calendarPath($externalCalendarId);

        self::assertCalendarDeleted($path, $this->api->withAccessToken($grant->tokens->accessToken, 'DELETE', $path));
    }

    public function revokeAuthorization(CalendarConnection $connection): void
    {
        self::assertRevoked($this->postRevocation($this->tokens->refreshTokenOf($connection->id)));
    }

    public function revokeGrant(CalendarGrant $grant): void
    {
        self::assertRevoked($this->postRevocation($grant->tokens->refreshToken));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function requestWithGrant(CalendarGrant $grant, string $method, string $path, array $options = []): Response
    {
        try {
            return $this->api->withAccessToken($grant->tokens->accessToken, $method, $path, $options);
        } catch (GoogleApiFailure) {
            throw CalendarAuthorizationFailed::calendarNotProvisioned();
        }
    }

    private static function assertCalendarDeleted(string $path, Response $response): void
    {
        if ($response->successful() || in_array($response->status(), self::GONE_STATUSES, true)) {
            return;
        }

        throw GoogleApiFailure::unexpectedStatus('DELETE', $path, $response->status());
    }

    private static function assertRevoked(Response $response): void
    {
        if ($response->successful() || $response->status() === Status::HTTP_BAD_REQUEST) {
            return;
        }

        throw GoogleApiFailure::unexpectedStatus('POST', GoogleOAuthClient::REVOKE_URL, $response->status());
    }

    private function postRevocation(#[SensitiveParameter] string $refreshToken): Response
    {
        try {
            return Http::asForm()
                ->timeout($this->timeoutSeconds)
                ->post(GoogleOAuthClient::REVOKE_URL, ['token' => $refreshToken]);
        } catch (Throwable $failure) {
            throw GoogleApiFailure::unreachable('POST', GoogleOAuthClient::REVOKE_URL, $failure::class);
        }
    }
}
