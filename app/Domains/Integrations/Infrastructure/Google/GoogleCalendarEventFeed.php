<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Contracts\CalendarEventFeed;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Exceptions\ExternalCalendarUnavailable;
use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\AllDayEventSpan;
use App\Domains\Integrations\ValueObjects\EventSpan;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\ExternalEventAvailability;
use App\Domains\Integrations\ValueObjects\ExternalEventOrigin;
use App\Domains\Integrations\ValueObjects\TimedEventSpan;
use DateMalformedStringException;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

final class GoogleCalendarEventFeed implements CalendarEventFeed
{
    private const CANCELLED = 'cancelled';

    private const TRANSPARENT = 'transparent';

    public function __construct(
        private readonly GoogleEventSource $source,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return list<ExternalCalendarEvent>
     */
    public function eventsBetween(CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        try {
            $items = $this->source->itemsBetween($connection, $from, $to);
        } catch (CalendarAuthorizationRevoked $revoked) {
            throw $revoked;
        } catch (Throwable $failure) {
            $this->logger->warning('Google Calendar busy times are unavailable; availability falls back to Mizita data.', [
                'calendar_connection_id' => $connection->id,
                'failure' => $failure instanceof GoogleApiFailure ? $failure->getMessage() : $failure::class,
            ]);

            throw ExternalCalendarUnavailable::forConnection($connection->id);
        }

        $events = [];

        foreach ($items as $item) {
            $event = self::eventFrom($item);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function eventFrom(array $item): ?ExternalCalendarEvent
    {
        try {
            return new ExternalCalendarEvent(
                span: self::spanOf($item),
                origin: self::originOf($item),
                availability: self::availabilityOf($item),
            );
        } catch (InvalidExternalCalendarEvent|DateMalformedStringException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     *
     * @throws InvalidExternalCalendarEvent
     * @throws DateMalformedStringException
     */
    private static function spanOf(array $item): EventSpan
    {
        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];

        if (is_string($start['dateTime'] ?? null) && is_string($end['dateTime'] ?? null)) {
            return new TimedEventSpan(new DateTimeImmutable($start['dateTime']), new DateTimeImmutable($end['dateTime']));
        }

        return new AllDayEventSpan((string) ($start['date'] ?? ''), (string) ($end['date'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function originOf(array $item): ExternalEventOrigin
    {
        $properties = $item['extendedProperties']['private'] ?? [];

        return is_array($properties) && isset($properties[GoogleCalendarEventPublisher::APPOINTMENT_PROPERTY])
            ? ExternalEventOrigin::PublishedByMizita
            : ExternalEventOrigin::AddedByHand;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function availabilityOf(array $item): ExternalEventAvailability
    {
        if (($item['status'] ?? null) === self::CANCELLED) {
            return ExternalEventAvailability::Cancelled;
        }

        if (($item['transparency'] ?? null) === self::TRANSPARENT) {
            return ExternalEventAvailability::Free;
        }

        return ExternalEventAvailability::Busy;
    }
}
