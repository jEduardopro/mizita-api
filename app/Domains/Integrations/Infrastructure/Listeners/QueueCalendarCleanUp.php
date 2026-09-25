<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Listeners;

use App\Domains\Integrations\Events\CalendarDisconnected;
use App\Domains\Integrations\Infrastructure\Queue\CleanUpDisconnectedCalendar;
use Illuminate\Contracts\Bus\Dispatcher;

final class QueueCalendarCleanUp
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(CalendarDisconnected $event): void
    {
        $this->bus->dispatch(new CleanUpDisconnectedCalendar($event->businessId, $event->connectionId));
    }
}
