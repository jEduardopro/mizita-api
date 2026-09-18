<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\ValueObjects\ServiceSnapshot;

final readonly class AppointmentServiceData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $color,
        public int $durationMinutes,
    ) {}

    public static function fromSnapshot(ServiceSnapshot $service): self
    {
        return new self(
            id: $service->id,
            name: $service->name,
            color: $service->color,
            durationMinutes: $service->durationMinutes,
        );
    }
}
