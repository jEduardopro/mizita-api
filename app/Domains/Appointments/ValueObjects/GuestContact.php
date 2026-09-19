<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

final readonly class GuestContact
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?GuestPhone $phone,
    ) {}
}
