<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

final readonly class NotifiedStaffMember
{
    public function __construct(
        public string $staffMemberId,
        public string $name,
    ) {}
}
