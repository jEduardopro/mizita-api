<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Services;

use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentChangesNotAllowed;
use App\Domains\Appointments\Exceptions\CancellationWindowClosed;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use DateTimeImmutable;

final class AppointmentChangeWindow
{
    /**
     * @throws AppointmentChangesNotAllowed
     * @throws CancellationWindowClosed
     */
    public function ensureOpenFor(
        Appointment $appointment,
        CancellationRule $rule,
        DateTimeImmutable $now,
    ): void {
        if (! $rule->isAllowed()) {
            throw AppointmentChangesNotAllowed::byPolicy();
        }

        if (! $rule->allowsChangeAt($appointment->slot()->startsAt, $now)) {
            throw CancellationWindowClosed::beforeStart();
        }
    }
}
