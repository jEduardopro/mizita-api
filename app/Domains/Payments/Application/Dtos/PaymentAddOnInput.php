<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentItemAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemName;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;

final readonly class PaymentAddOnInput
{
    private const MINIMUM_AMOUNT_CENTS = 0;

    public function __construct(
        public string $name,
        public int $amountCents,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            name: self::textOrEmpty($payload['name'] ?? null),
            amountCents: self::centsOrZero($payload['amount_cents'] ?? null),
        );
    }

    /**
     * @throws InvalidPaymentItemName
     * @throws InvalidPaymentItemAmount
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateAmountCents();
    }

    /**
     * @throws InvalidPaymentItemName
     */
    public function toName(): PaymentItemName
    {
        return PaymentItemName::fromString($this->name);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function centsOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function validateName(): void
    {
        $this->toName();
    }

    private function validateAmountCents(): void
    {
        if ($this->amountCents < self::MINIMUM_AMOUNT_CENTS) {
            throw InvalidPaymentItemAmount::negative($this->amountCents);
        }

        if ($this->amountCents > Money::MAXIMUM_CENTS) {
            throw InvalidPaymentItemAmount::tooLarge($this->amountCents, Money::MAXIMUM_CENTS);
        }
    }
}
