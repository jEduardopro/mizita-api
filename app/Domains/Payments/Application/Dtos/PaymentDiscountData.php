<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\Money;

final readonly class PaymentDiscountData
{
    public function __construct(
        public DiscountType $type,
        public int $value,
        public int $amountCents,
    ) {}

    public static function of(Discount $discount, Money $amount): self
    {
        return new self(
            type: $discount->type,
            value: $discount->value,
            amountCents: $amount->amount,
        );
    }
}
