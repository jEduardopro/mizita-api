<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

final readonly class CustomerSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
    ) {}
}
