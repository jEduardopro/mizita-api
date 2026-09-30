<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

use DateTimeImmutable;

final readonly class RegistrationWindow
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}
}
