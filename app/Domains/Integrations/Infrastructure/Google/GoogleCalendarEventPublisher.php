<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Contracts\CalendarEventPublisher;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\ValueObjects\CalendarEventDraft;
use Illuminate\Http\Client\Response;
use Symfony\Component\HttpFoundation\Response as Status;

final class GoogleCalendarEventPublisher implements CalendarEventPublisher
{
    public const APPOINTMENT_PROPERTY = 'mizita_appointment_id';

    private const CONFIRMED = 'confirmed';

    private const GONE_STATUSES = [Status::HTTP_NOT_FOUND, Status::HTTP_GONE];

    public function __construct(
        private readonly GoogleCalendarApi $api,
    ) {}

    public function publish(CalendarConnection $connection, ?string $knownEventId, CalendarEventDraft $draft): string
    {
        $payload = self::payloadFor($draft);

        return $this->patchKnownEvent($connection, $knownEventId, $payload)
            ?? $this->patchTaggedEvent($connection, $draft->appointmentId, $payload)
            ?? $this->insert($connection, $payload);
    }

    public function withdraw(CalendarConnection $connection, string $externalEventId): void
    {
        $path = GoogleCalendarApi::eventPath($connection->externalCalendarId(), $externalEventId);
        $response = $this->api->forConnection($connection->id, 'DELETE', $path);

        if ($response->successful() || self::isGone($response)) {
            return;
        }

        throw GoogleApiFailure::unexpectedStatus('DELETE', $path, $response->status());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function patchKnownEvent(CalendarConnection $connection, ?string $knownEventId, array $payload): ?string
    {
        if ($knownEventId === null) {
            return null;
        }

        return $this->patch($connection, $knownEventId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function patchTaggedEvent(CalendarConnection $connection, string $appointmentId, array $payload): ?string
    {
        $taggedEventId = $this->taggedEventIdFor($connection, $appointmentId);

        if ($taggedEventId === null) {
            return null;
        }

        return $this->patch($connection, $taggedEventId, $payload);
    }

    private function taggedEventIdFor(CalendarConnection $connection, string $appointmentId): ?string
    {
        $path = GoogleCalendarApi::calendarPath($connection->externalCalendarId()).'/events';
        $response = $this->api->forConnection($connection->id, 'GET', $path, [
            'query' => [
                'privateExtendedProperty' => self::APPOINTMENT_PROPERTY.'='.$appointmentId,
                'showDeleted' => 'true',
                'maxResults' => 1,
            ],
        ]);

        if (! $response->successful()) {
            throw GoogleApiFailure::unexpectedStatus('GET', $path, $response->status());
        }

        $eventId = $response->json('items.0.id');

        return is_string($eventId) && $eventId !== '' ? $eventId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function patch(CalendarConnection $connection, string $eventId, array $payload): ?string
    {
        $path = GoogleCalendarApi::eventPath($connection->externalCalendarId(), $eventId);
        $response = $this->api->forConnection($connection->id, 'PATCH', $path, ['json' => $payload]);

        if (self::isGone($response)) {
            return null;
        }

        return self::eventIdFrom($response, 'PATCH', $path);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insert(CalendarConnection $connection, array $payload): string
    {
        $path = GoogleCalendarApi::calendarPath($connection->externalCalendarId()).'/events';
        $response = $this->api->forConnection($connection->id, 'POST', $path, ['json' => $payload]);

        return self::eventIdFrom($response, 'POST', $path);
    }

    private static function eventIdFrom(Response $response, string $method, string $path): string
    {
        $eventId = $response->successful() ? $response->json('id') : null;

        if (! is_string($eventId) || $eventId === '') {
            throw GoogleApiFailure::unexpectedStatus($method, $path, $response->status());
        }

        return $eventId;
    }

    private static function isGone(Response $response): bool
    {
        return in_array($response->status(), self::GONE_STATUSES, true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadFor(CalendarEventDraft $draft): array
    {
        return [
            'summary' => $draft->title,
            'description' => $draft->description,
            'status' => self::CONFIRMED,
            'start' => ['dateTime' => $draft->startsAt->format(DATE_ATOM), 'timeZone' => $draft->timezone],
            'end' => ['dateTime' => $draft->endsAt->format(DATE_ATOM), 'timeZone' => $draft->timezone],
            'extendedProperties' => ['private' => [self::APPOINTMENT_PROPERTY => $draft->appointmentId]],
        ];
    }
}
