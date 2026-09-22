<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;
use DateTimeImmutable;

final readonly class PaymentTransactionData
{
    public function __construct(
        public string $id,
        public string $paymentMethodId,
        public string $paymentMethodCode,
        public int $amountCents,
        public PaymentTransactionStatus $status,
        public DateTimeImmutable $processedAt,
        public ?DateTimeImmutable $voidedAt,
    ) {}

    public static function fromTransaction(PaymentTransaction $transaction, PaymentMethod $method): self
    {
        return new self(
            id: $transaction->id,
            paymentMethodId: $transaction->paymentMethodId,
            paymentMethodCode: $method->code,
            amountCents: $transaction->amount->amount,
            status: $transaction->status(),
            processedAt: $transaction->processedAt,
            voidedAt: $transaction->voidedAt(),
        );
    }
}
