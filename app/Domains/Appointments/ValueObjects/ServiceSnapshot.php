<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

final readonly class ServiceSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public string $color,
        public int $durationMinutes,
        public bool $active,
    ) {}
}
