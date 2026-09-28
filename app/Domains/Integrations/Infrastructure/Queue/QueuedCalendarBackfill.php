<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Queue;

use App\Domains\Integrations\Contracts\CalendarBackfillQueue;
use Illuminate\Contracts\Bus\Dispatcher;

final class QueuedCalendarBackfill implements CalendarBackfillQueue
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function schedule(string $businessId, string $connectionId): void
    {
        $this->bus->dispatch(new BackfillCalendar($businessId, $connectionId));
    }
}
