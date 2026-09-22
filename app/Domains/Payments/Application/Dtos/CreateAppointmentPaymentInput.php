<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemName;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Exceptions\TooManyPaymentItems;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\Identifier;
use App\Domains\Payments\ValueObjects\Money;

final readonly class CreateAppointmentPaymentInput
{
    public const MAXIMUM_ADD_ONS = 50;

    private const MINIMUM_TRANSACTION_CENTS = 1;

    /**
     * @param  list<PaymentAddOnInput>  $addOns
     */
    public function __construct(
        public string $appointmentId,
        public array $addOns,
        public ?DiscountInput $discount,
        public string $paymentMethodId,
        public int $amountCents,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $appointmentId): self
    {
        return new self(
            appointmentId: $appointmentId,
            addOns: self::addOnsFrom($payload['add_ons'] ?? null),
            discount: self::discountFrom($payload['discount'] ?? null),
            paymentMethodId: self::textOrEmpty($payload['payment_method_id'] ?? null),
            amountCents: self::centsOrZero($payload['amount_cents'] ?? null),
        );
    }

    /**
     * @throws PaymentAppointmentNotFound
     * @throws PaymentMethodNotFound
     * @throws TooManyPaymentItems
     * @throws InvalidPaymentItemName
     * @throws InvalidPaymentItemAmount
     * @throws InvalidPaymentDiscount
     * @throws InvalidTransactionAmount
     */
    public function validate(): void
    {
        $this->validateAppointmentId();
        $this->validatePaymentMethodId();
        $this->validateAddOns();
        $this->discount?->validate();
        $this->validateAmountCents();
    }

    /**
     * @throws InvalidPaymentDiscount
     */
    public function toDiscount(): Discount
    {
        return $this->discount?->toDiscount() ?? Discount::none();
    }

    /**
     * @return list<PaymentAddOnInput>
     */
    private static function addOnsFrom(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $addOn): PaymentAddOnInput => PaymentAddOnInput::fromRequest(
                is_array($addOn) ? $addOn : [],
            ),
            $value,
        ));
    }

    private static function discountFrom(mixed $value): ?DiscountInput
    {
        if (! is_array($value)) {
            return null;
        }

        return DiscountInput::fromRequest($value);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function centsOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function validateAppointmentId(): void
    {
        if (! Identifier::isWellFormed($this->appointmentId)) {
            throw PaymentAppointmentNotFound::withId($this->appointmentId);
        }
    }

    private function validatePaymentMethodId(): void
    {
        if (! Identifier::isWellFormed($this->paymentMethodId)) {
            throw PaymentMethodNotFound::withId($this->paymentMethodId);
        }
    }

    private function validateAddOns(): void
    {
        if (count($this->addOns) > self::MAXIMUM_ADD_ONS) {
            throw TooManyPaymentItems::atMost(self::MAXIMUM_ADD_ONS);
        }

        foreach ($this->addOns as $addOn) {
            $addOn->validate();
        }
    }

    private function validateAmountCents(): void
    {
        if ($this->amountCents < self::MINIMUM_TRANSACTION_CENTS) {
            throw InvalidTransactionAmount::notPositive($this->amountCents);
        }

        if ($this->amountCents > Money::MAXIMUM_CENTS) {
            throw InvalidTransactionAmount::tooLarge($this->amountCents, Money::MAXIMUM_CENTS);
        }
    }
}
