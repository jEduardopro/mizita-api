<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Exceptions\CalendarAppointmentNotFound;
use App\Domains\Integrations\ValueObjects\AppointmentSnapshot;

interface AppointmentFeed
{
    /**
     * @throws CalendarAppointmentNotFound
     */
    public function snapshotOf(string $appointmentId): AppointmentSnapshot;
}
