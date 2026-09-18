<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;

final readonly class AppointmentStaffData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromSnapshot(StaffMemberSnapshot $member): self
    {
        return new self(
            id: $member->id,
            name: $member->name,
        );
    }
}
