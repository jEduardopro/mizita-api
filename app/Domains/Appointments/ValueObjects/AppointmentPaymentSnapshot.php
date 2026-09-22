<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

final readonly class AppointmentPaymentSnapshot
{
    public function __construct(
        public string $id,
        public AppointmentPaymentStatus $status,
        public int $totalCents,
        public int $paidCents,
        public int $balanceCents,
        public string $currencyCode,
    ) {}
}
