<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Entities\CalendarConnection;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

final class CachedGoogleEventSource implements GoogleEventSource
{
    private const KEY_PREFIX = 'integrations:google-calendar:busy:';

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
        return $this->cache->remember(
            self::keyFor($connection, $from, $to),
            $this->ttlSeconds,
            fn (): array => $this->source->itemsBetween($connection, $from, $to),
        );
    }

    private static function keyFor(CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        return self::KEY_PREFIX.$connection->id.':'.hash('sha256', implode('|', [
            $connection->externalCalendarId(),
            $from->getTimestamp(),
            $to->getTimestamp(),
        ]));
    }
}
