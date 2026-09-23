<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentActor;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\ValueObjects\Identifier;
use App\Domains\Payments\ValueObjects\Money;

final readonly class RecordPaymentTransactionInput
{
    private const MINIMUM_TRANSACTION_CENTS = 1;

    public function __construct(
        public string $paymentId,
        public string $paymentMethodId,
        public int $amountCents,
        public string $actorAccountId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $paymentId, string $actorAccountId): self
    {
        return new self(
            paymentId: $paymentId,
            paymentMethodId: self::textOrEmpty($payload['payment_method_id'] ?? null),
            amountCents: self::centsOrZero($payload['amount_cents'] ?? null),
            actorAccountId: $actorAccountId,
        );
    }

    /**
     * @throws PaymentNotFound
     * @throws PaymentMethodNotFound
     * @throws InvalidTransactionAmount
     * @throws InvalidPaymentActor
     */
    public function validate(): void
    {
        $this->validatePaymentId();
        $this->validatePaymentMethodId();
        $this->validateAmountCents();
        $this->validateActorAccountId();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function centsOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function validatePaymentId(): void
    {
        if (! Identifier::isWellFormed($this->paymentId)) {
            throw PaymentNotFound::withId($this->paymentId);
        }
    }

    private function validatePaymentMethodId(): void
    {
        if (! Identifier::isWellFormed($this->paymentMethodId)) {
            throw PaymentMethodNotFound::withId($this->paymentMethodId);
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

    private function validateActorAccountId(): void
    {
        if (! Identifier::isWellFormed($this->actorAccountId)) {
            throw InvalidPaymentActor::malformed($this->actorAccountId);
        }
    }
}
