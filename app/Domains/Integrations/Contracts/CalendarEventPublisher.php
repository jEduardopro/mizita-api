<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\ValueObjects\CalendarEventDraft;

interface CalendarEventPublisher
{
    /**
     * @throws CalendarAuthorizationRevoked
     */
    public function publish(CalendarConnection $connection, ?string $knownEventId, CalendarEventDraft $draft): string;

    /**
     * @throws CalendarAuthorizationRevoked
     */
    public function withdraw(CalendarConnection $connection, string $externalEventId): void;
}
