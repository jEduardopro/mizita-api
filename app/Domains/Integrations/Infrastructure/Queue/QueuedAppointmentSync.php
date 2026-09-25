<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Queue;

use App\Domains\Integrations\Contracts\AppointmentSyncQueue;
use Illuminate\Contracts\Bus\Dispatcher;

final class QueuedAppointmentSync implements AppointmentSyncQueue
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function schedule(string $appointmentId): void
    {
        $this->bus->dispatch(new SyncAppointmentToCalendar($appointmentId));
    }
}
