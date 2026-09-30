<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

final readonly class StaffPerformance
{
    public function __construct(
        public string $staffMemberId,
        public string $name,
        public string $email,
        public int $collectedCents,
        public int $attendedAppointments,
    ) {}
}
