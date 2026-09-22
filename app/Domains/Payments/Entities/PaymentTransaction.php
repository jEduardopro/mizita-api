<?php

declare(strict_types=1);

namespace App\Domains\Payments\Entities;

use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\Exceptions\PaymentTransactionAlreadyVoided;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;
use DateTimeImmutable;

final class PaymentTransaction
{
    private function __construct(
        public readonly string $id,
        public readonly string $paymentMethodId,
        public readonly Money $amount,
        public readonly DateTimeImmutable $processedAt,
        private ?DateTimeImmutable $voidedAt,
        private ?string $voidedByAccountId,
    ) {}

    public static function record(
        string $id,
        string $paymentMethodId,
        Money $amount,
        DateTimeImmutable $processedAt,
    ): self {
        return new self($id, $paymentMethodId, $amount, $processedAt, null, null);
    }

    public static function restore(
        string $id,
        string $paymentMethodId,
        Money $amount,
        DateTimeImmutable $processedAt,
        ?DateTimeImmutable $voidedAt,
        ?string $voidedByAccountId,
    ): self {
        return new self($id, $paymentMethodId, $amount, $processedAt, $voidedAt, $voidedByAccountId);
    }

    /**
     * @throws InvalidVoidActor
     * @throws PaymentTransactionAlreadyVoided
     */
    public function void(string $accountId, DateTimeImmutable $now): void
    {
        if ($this->isVoided()) {
            throw PaymentTransactionAlreadyVoided::withId($this->id);
        }

        if (trim($accountId) === '') {
            throw InvalidVoidActor::empty();
        }

        $this->voidedAt = $now;
        $this->voidedByAccountId = $accountId;
    }

    public function isVoided(): bool
    {
        return $this->voidedAt !== null;
    }

    public function status(): PaymentTransactionStatus
    {
        return $this->isVoided()
            ? PaymentTransactionStatus::Voided
            : PaymentTransactionStatus::Completed;
    }

    public function voidedAt(): ?DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function voidedByAccountId(): ?string
    {
        return $this->voidedByAccountId;
    }
}
