<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Entities\CalendarConnection;
use DateTimeImmutable;

final class HttpGoogleEventSource implements GoogleEventSource
{
    private const PAGE_SIZE = 250;

    private const MAXIMUM_PAGES = 4;

    private const FIELDS = 'items(id,status,transparency,start,end,extendedProperties/private),nextPageToken';

    public function __construct(
        private readonly GoogleCalendarApi $api,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsBetween(CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $path = GoogleCalendarApi::calendarPath($connection->externalCalendarId()).'/events';
        $items = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAXIMUM_PAGES; $page++) {
            $response = $this->api->forConnection($connection->id, 'GET', $path, [
                'query' => array_filter([
                    'singleEvents' => 'true',
                    'showDeleted' => 'false',
                    'timeMin' => $from->format(DATE_ATOM),
                    'timeMax' => $to->format(DATE_ATOM),
                    'maxResults' => self::PAGE_SIZE,
                    'fields' => self::FIELDS,
                    'pageToken' => $pageToken,
                ], static fn (mixed $value): bool => $value !== null),
            ]);

            if (! $response->successful()) {
                throw GoogleApiFailure::unexpectedStatus('GET', $path, $response->status());
            }

            $items = [...$items, ...self::itemsOf($response->json('items'))];
            $pageToken = $response->json('nextPageToken');

            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function itemsOf(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, is_array(...)));
    }
}
