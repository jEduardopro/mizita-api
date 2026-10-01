<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use DateTimeImmutable;

final readonly class SaleRecord
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
}
