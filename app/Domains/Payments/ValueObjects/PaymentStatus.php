<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum PaymentStatus: string
{
    case Pending = 'pending';

    case PartiallyPaid = 'partially_paid';

    case Paid = 'paid';
}
