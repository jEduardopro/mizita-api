<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Listeners;

use App\Domains\Integrations\Events\CalendarConnected;
use App\Domains\Integrations\Infrastructure\Queue\BackfillCalendar;
use Illuminate\Contracts\Bus\Dispatcher;

final class QueueCalendarBackfill
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(CalendarConnected $event): void
    {
        $this->bus->dispatch(new BackfillCalendar($event->businessId, $event->connectionId));
    }
}
