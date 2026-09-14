<?php

declare(strict_types=1);

namespace App\Domains\Staff\Events;

use App\Domains\Staff\ValueObjects\StaffRole;

final readonly class StaffMemberRegistered
{
    public function __construct(
        public string $id,
        public string $businessId,
        public StaffRole $role,
    ) {}
}
