<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\SaleRecord;
use DateTimeImmutable;

final readonly class SaleData
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $createdAt,
        public string $customerId,
        public string $customerName,
        public PaymentStatus $status,
        public int $totalCents,
        public string $currencyCode,
        public string $referenceCode,
    ) {}

    public static function fromRecord(SaleRecord $sale): self
    {
        return new self(
            id: $sale->id,
            createdAt: $sale->createdAt,
            customerId: $sale->customerId,
            customerName: $sale->customerName,
            status: $sale->status,
            totalCents: $sale->totalCents,
            currencyCode: $sale->currencyCode,
            referenceCode: $sale->referenceCode,
        );
    }
}
