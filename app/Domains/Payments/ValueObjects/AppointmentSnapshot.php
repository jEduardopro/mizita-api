<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

final readonly class AppointmentSnapshot
{
    public function __construct(
        public string $id,
        public string $serviceId,
        public bool $cancelled,
        public string $staffMemberId,
    ) {}
}
