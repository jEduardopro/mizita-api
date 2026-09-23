<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\DiscountExceedsSubtotal;
use App\Shared\ValueObjects\CurrencyCode;

final readonly class PaymentBreakdown
{
    private function __construct(
        public Money $subtotalPreDiscount,
        public Discount $discount,
        public Money $discountAmount,
        public Money $subtotal,
    ) {}

    /**
     * @throws DiscountExceedsSubtotal
     */
    public static function of(Money $subtotalPreDiscount, Discount $discount): self
    {
        $discountAmount = $discount->amountOf($subtotalPreDiscount);

        return new self(
            subtotalPreDiscount: $subtotalPreDiscount,
            discount: $discount,
            discountAmount: $discountAmount,
            subtotal: $subtotalPreDiscount->minus($discountAmount),
        );
    }

    public static function none(CurrencyCode $currency): self
    {
        $zero = Money::zero($currency);

        return new self(
            subtotalPreDiscount: $zero,
            discount: Discount::none(),
            discountAmount: $zero,
            subtotal: $zero,
        );
    }

    public static function restore(
        Money $subtotalPreDiscount,
        Discount $discount,
        Money $discountAmount,
        Money $subtotal,
    ): self {
        return new self($subtotalPreDiscount, $discount, $discountAmount, $subtotal);
    }

    public function discountType(): DiscountType
    {
        return $this->discount->type;
    }

    public function discountValue(): int
    {
        return $this->discount->value;
    }
}
