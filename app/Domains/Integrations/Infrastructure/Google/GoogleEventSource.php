<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use DateTimeImmutable;

interface GoogleEventSource
{
    /**
     * @return list<array<string, mixed>>
     *
     * @throws CalendarAuthorizationRevoked
     * @throws GoogleApiFailure
     */
    public function itemsBetween(CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
