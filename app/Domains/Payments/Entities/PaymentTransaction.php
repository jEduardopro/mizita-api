<?php

declare(strict_types=1);

namespace App\Domains\Payments\Entities;

use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentBreakdown;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use DateTimeImmutable;

final class PaymentTransaction
{
    private function __construct(
        public readonly string $id,
        public readonly PaymentTransactionType $type,
        public readonly string $paymentMethodId,
        public readonly ?string $accountId,
        public readonly PaymentBreakdown $breakdown,
        public readonly Money $total,
        public readonly DateTimeImmutable $processedAt,
    ) {}

    public static function approve(
        string $id,
        string $paymentMethodId,
        ?string $accountId,
        PaymentBreakdown $breakdown,
        Money $total,
        DateTimeImmutable $processedAt,
    ): self {
        return new self(
            id: $id,
            type: PaymentTransactionType::Approved,
            paymentMethodId: $paymentMethodId,
            accountId: $accountId,
            breakdown: $breakdown,
            total: $total,
            processedAt: $processedAt,
        );
    }

    /**
     * @throws InvalidVoidActor
     */
    public static function void(
        string $id,
        string $paymentMethodId,
        string $accountId,
        Money $total,
        DateTimeImmutable $processedAt,
    ): self {
        if (trim($accountId) === '') {
            throw InvalidVoidActor::empty();
        }

        return new self(
            id: $id,
            type: PaymentTransactionType::Void,
            paymentMethodId: $paymentMethodId,
            accountId: $accountId,
            breakdown: PaymentBreakdown::none($total->currency),
            total: $total,
            processedAt: $processedAt,
        );
    }

    public static function restore(
        string $id,
        PaymentTransactionType $type,
        string $paymentMethodId,
        ?string $accountId,
        PaymentBreakdown $breakdown,
        Money $total,
        DateTimeImmutable $processedAt,
    ): self {
        return new self($id, $type, $paymentMethodId, $accountId, $breakdown, $total, $processedAt);
    }
}
