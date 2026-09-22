<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\DiscountExceedsSubtotal;
use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;

final readonly class Discount
{
    public const MAXIMUM_BASIS_POINTS = 10_000;

    private const ROUNDING_OFFSET = 5_000;

    private function __construct(
        public DiscountType $type,
        public int $value,
    ) {}

    public static function none(): self
    {
        return new self(DiscountType::None, 0);
    }

    /**
     * @throws InvalidPaymentDiscount
     */
    public static function ofPercentage(int $basisPoints): self
    {
        if ($basisPoints < 0 || $basisPoints > self::MAXIMUM_BASIS_POINTS) {
            throw InvalidPaymentDiscount::percentageOutOfRange($basisPoints);
        }

        return new self(DiscountType::Percentage, $basisPoints);
    }

    /**
     * @throws InvalidPaymentDiscount
     */
    public static function ofAmount(int $cents): self
    {
        if ($cents < 0) {
            throw InvalidPaymentDiscount::negativeAmount($cents);
        }

        return new self(DiscountType::Fixed, $cents);
    }

    public static function restore(DiscountType $type, int $value): self
    {
        return new self($type, $value);
    }

    /**
     * @throws DiscountExceedsSubtotal
     */
    public function amountOf(Money $subtotal): Money
    {
        return match ($this->type) {
            DiscountType::None => Money::zero($subtotal->currency),
            DiscountType::Percentage => Money::fromCents($this->percentageOf($subtotal), $subtotal->currency),
            DiscountType::Fixed => $this->fixedWithin($subtotal),
        };
    }

    public function isNone(): bool
    {
        return $this->type === DiscountType::None;
    }

    private function percentageOf(Money $subtotal): int
    {
        return intdiv($subtotal->amount * $this->value + self::ROUNDING_OFFSET, self::MAXIMUM_BASIS_POINTS);
    }

    /**
     * @throws DiscountExceedsSubtotal
     */
    private function fixedWithin(Money $subtotal): Money
    {
        if ($this->value > $subtotal->amount) {
            throw DiscountExceedsSubtotal::of($this->value, $subtotal->amount);
        }

        return Money::fromCents($this->value, $subtotal->currency);
    }
}
