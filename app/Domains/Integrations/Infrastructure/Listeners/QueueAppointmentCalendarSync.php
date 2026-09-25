<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Listeners;

use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Appointments\Events\AppointmentCancelled;
use App\Domains\Appointments\Events\AppointmentCreated;
use App\Domains\Appointments\Events\AppointmentDeleted;
use App\Domains\Appointments\Events\AppointmentRescheduled;
use App\Domains\Appointments\Events\AppointmentUpdated;
use App\Domains\Integrations\Contracts\AppointmentSyncQueue;

final class QueueAppointmentCalendarSync
{
    public function __construct(
        private readonly AppointmentSyncQueue $syncQueue,
    ) {}

    public function handle(
        AppointmentCreated|AppointmentBooked|AppointmentUpdated|AppointmentRescheduled|AppointmentCancelled|AppointmentDeleted $event,
    ): void {
        $this->syncQueue->schedule($event->id);
    }
}
