<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Events;

final readonly class AppointmentCreated
{
    public function __construct(
        public string $id,
    ) {}
}
