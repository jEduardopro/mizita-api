<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\TransactionRecord;
use DateTimeImmutable;

final readonly class PaymentTransactionReportData
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $processedAt,
        public string $customerId,
        public string $customerName,
        public int $amountCents,
        public string $currencyCode,
        public PaymentTransactionType $type,
        public string $paymentMethodCode,
    ) {}

    public static function fromRecord(TransactionRecord $transaction): self
    {
        return new self(
            id: $transaction->id,
            processedAt: $transaction->processedAt,
            customerId: $transaction->customerId,
            customerName: $transaction->customerName,
            amountCents: $transaction->signedAmountCents(),
            currencyCode: $transaction->currencyCode,
            type: $transaction->type,
            paymentMethodCode: $transaction->paymentMethodCode,
        );
    }
}
