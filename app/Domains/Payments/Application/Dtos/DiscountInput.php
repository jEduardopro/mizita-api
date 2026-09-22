<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;

final readonly class DiscountInput
{
    public function __construct(
        public string $type,
        public int $value,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            type: self::textOrEmpty($payload['type'] ?? null),
            value: self::valueOrZero($payload['value'] ?? null),
        );
    }

    /**
     * @throws InvalidPaymentDiscount
     */
    public function validate(): void
    {
        $this->validateType();
        $this->validateValue();
    }

    /**
     * @throws InvalidPaymentDiscount
     */
    public function toDiscount(): Discount
    {
        return match ($this->toType()) {
            DiscountType::None => Discount::none(),
            DiscountType::Percentage => Discount::ofPercentage($this->value),
            DiscountType::Fixed => Discount::ofAmount($this->value),
        };
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function valueOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function validateType(): void
    {
        $this->toType();
    }

    private function validateValue(): void
    {
        $this->toDiscount();
    }

    /**
     * @throws InvalidPaymentDiscount
     */
    private function toType(): DiscountType
    {
        return DiscountType::tryFrom($this->type)
            ?? throw InvalidPaymentDiscount::unknownType($this->type);
    }
}
