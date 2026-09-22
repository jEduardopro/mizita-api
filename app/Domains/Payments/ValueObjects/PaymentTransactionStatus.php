<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum PaymentTransactionStatus: string
{
    case Completed = 'completed';

    case Voided = 'voided';
}
