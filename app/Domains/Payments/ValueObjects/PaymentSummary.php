<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

final readonly class PaymentSummary
{
    public function __construct(
        public string $appointmentId,
        public string $paymentId,
        public PaymentStatus $status,
        public int $totalCents,
        public int $paidCents,
        public string $currencyCode,
    ) {}
}
