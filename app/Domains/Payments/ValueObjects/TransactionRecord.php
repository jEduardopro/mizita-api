<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use DateTimeImmutable;

final readonly class TransactionRecord
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $processedAt,
        public string $customerId,
        public string $customerName,
        public PaymentTransactionType $type,
        public int $totalCents,
        public string $currencyCode,
        public string $paymentMethodCode,
    ) {}

    public function signedAmountCents(): int
    {
        return $this->type->signedAmount($this->totalCents);
    }
}
