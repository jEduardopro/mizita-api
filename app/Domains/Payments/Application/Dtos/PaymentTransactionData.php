<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use DateTimeImmutable;

final readonly class PaymentTransactionData
{
    public function __construct(
        public string $id,
        public PaymentTransactionType $type,
        public string $paymentMethodId,
        public string $paymentMethodCode,
        public int $subtotalPreDiscountCents,
        public DiscountType $discountType,
        public int $discountValue,
        public int $subtotalDiscountCents,
        public int $subtotalCents,
        public int $totalCents,
        public DateTimeImmutable $processedAt,
    ) {}

    public static function fromTransaction(PaymentTransaction $transaction, PaymentMethod $method): self
    {
        $breakdown = $transaction->breakdown;

        return new self(
            id: $transaction->id,
            type: $transaction->type,
            paymentMethodId: $transaction->paymentMethodId,
            paymentMethodCode: $method->code,
            subtotalPreDiscountCents: $breakdown->subtotalPreDiscount->amount,
            discountType: $breakdown->discountType(),
            discountValue: $breakdown->discountValue(),
            subtotalDiscountCents: $breakdown->discountAmount->amount,
            subtotalCents: $breakdown->subtotal->amount,
            totalCents: $transaction->total->amount,
            processedAt: $transaction->processedAt,
        );
    }
}
