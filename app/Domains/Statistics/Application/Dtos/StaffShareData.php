<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

final readonly class StaffShareData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $collectedCents,
        public float $sharePercent,
        public int $attendedAppointments,
    ) {}
}
