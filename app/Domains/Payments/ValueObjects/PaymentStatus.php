<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum PaymentStatus: string
{
    case Pending = 'pending';

    case PartiallyPaid = 'partially_paid';

    case Paid = 'paid';

    private const NOTHING_PAID = 0;

    public static function forAmounts(int $paidCents, int $totalCents): self
    {
        if ($paidCents >= $totalCents) {
            return self::Paid;
        }

        if ($paidCents === self::NOTHING_PAID) {
            return self::Pending;
        }

        return self::PartiallyPaid;
    }
}
