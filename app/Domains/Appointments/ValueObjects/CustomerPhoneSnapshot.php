<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

final readonly class CustomerPhoneSnapshot
{
    public function __construct(
        public string $countryCode,
        public string $nationalNumber,
    ) {}
}
