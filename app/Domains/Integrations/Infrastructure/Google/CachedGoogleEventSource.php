<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Entities\CalendarConnection;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as Cache;

final class CachedGoogleEventSource implements GoogleEventSource
{
    private const KEY_PREFIX = 'integrations:google-calendar:busy:';

    private const BUCKET_ZONE = 'UTC';

    private const FIRST_DAY_OF_MONTH = 'first day of this month';

    private const ONE_MONTH = 'P1M';

    private const ITEM_IDENTITY = 'id';

    private const ANONYMOUS_ITEM_HASH = 'xxh128';

    public function __construct(
        private readonly GoogleEventSource $source,
        private readonly Cache $cache,
        private readonly int $ttlSeconds,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsBetween(CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $itemsByIdentity = [];

        foreach (self::monthsCovering($from, $to) as $monthStart) {
            foreach ($this->itemsOfMonth($connection, $monthStart) as $item) {
                $itemsByIdentity[self::identityOf($item)] = $item;
            }
        }

        return array_values(array_filter($itemsByIdentity, (new GoogleEventWindow($from, $to))->mayOverlap(...)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemsOfMonth(CalendarConnection $connection, DateTimeImmutable $monthStart): array
    {
        return $this->cache->remember(
            self::keyFor($connection, $monthStart),
            $this->ttlSeconds,
            fn (): array => $this->source->itemsBetween($connection, $monthStart, self::monthAfter($monthStart)),
        );
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private static function monthsCovering(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $end = $to->setTimezone(new DateTimeZone(self::BUCKET_ZONE));
        $monthStart = $from
            ->setTimezone(new DateTimeZone(self::BUCKET_ZONE))
            ->modify(self::FIRST_DAY_OF_MONTH)
            ->setTime(0, 0);
        $months = [];

        while ($monthStart < $end) {
            $months[] = $monthStart;
            $monthStart = self::monthAfter($monthStart);
        }

        return $months;
    }

    private static function monthAfter(DateTimeImmutable $monthStart): DateTimeImmutable
    {
        return $monthStart->add(new DateInterval(self::ONE_MONTH));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function identityOf(array $item): string
    {
        $identity = $item[self::ITEM_IDENTITY] ?? null;

        if (is_string($identity)) {
            return $identity;
        }

        return hash(self::ANONYMOUS_ITEM_HASH, serialize($item));
    }

    private static function keyFor(CalendarConnection $connection, DateTimeImmutable $monthStart): string
    {
        return self::KEY_PREFIX.$connection->id.':'.hash('sha256', implode('|', [
            $connection->externalCalendarId(),
            $monthStart->getTimestamp(),
        ]));
    }
}
