<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum PaymentTransactionType: string
{
    case Approved = 'approved';

    case Void = 'void';

    case Refund = 'refund';

    case Failed = 'failed';

    public function countsTowardsPaid(): bool
    {
        return $this === self::Approved;
    }

    public function reversesPaid(): bool
    {
        return $this === self::Void || $this === self::Refund;
    }
}
