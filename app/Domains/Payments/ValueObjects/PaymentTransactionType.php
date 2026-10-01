<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum PaymentTransactionType: string
{
    case Approved = 'approved';

    case Void = 'void';

    case Refund = 'refund';

    case Failed = 'failed';

    private const REVERSING_SIGN = -1;

    private const RECORDED_SIGN = 1;

    public function sign(): int
    {
        return $this === self::Void ? self::REVERSING_SIGN : self::RECORDED_SIGN;
    }

    public function signedAmount(int $cents): int
    {
        return $this->sign() * $cents;
    }

    public function countsTowardsPaid(): bool
    {
        return $this === self::Approved;
    }

    public function reversesPaid(): bool
    {
        return $this === self::Void || $this === self::Refund;
    }
}
