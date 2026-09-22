<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\Exceptions\PaymentTransactionNotFound;
use App\Domains\Payments\ValueObjects\Identifier;

final readonly class VoidPaymentTransactionInput
{
    public function __construct(
        public string $paymentId,
        public string $transactionId,
        public string $actorAccountId,
    ) {}

    /**
     * @throws PaymentNotFound
     * @throws PaymentTransactionNotFound
     * @throws InvalidVoidActor
     */
    public function validate(): void
    {
        $this->validatePaymentId();
        $this->validateTransactionId();
        $this->validateActorAccountId();
    }

    private function validatePaymentId(): void
    {
        if (! Identifier::isWellFormed($this->paymentId)) {
            throw PaymentNotFound::withId($this->paymentId);
        }
    }

    private function validateTransactionId(): void
    {
        if (! Identifier::isWellFormed($this->transactionId)) {
            throw PaymentTransactionNotFound::withId($this->transactionId);
        }
    }

    private function validateActorAccountId(): void
    {
        if (! Identifier::isWellFormed($this->actorAccountId)) {
            throw InvalidVoidActor::malformed($this->actorAccountId);
        }
    }
}
