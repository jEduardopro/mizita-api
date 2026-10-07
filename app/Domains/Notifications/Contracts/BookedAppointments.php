<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Contracts;

use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\ValueObjects\BookedAppointment;

interface BookedAppointments
{
    /**
     * @throws NotifiedAppointmentNotFound
     */
    public function recipientOf(string $appointmentId): BookedAppointment;
}
