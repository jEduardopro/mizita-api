<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

enum AppointmentPaymentStatus: string
{
    case Pending = 'pending';

    case PartiallyPaid = 'partially_paid';

    case Paid = 'paid';
}
