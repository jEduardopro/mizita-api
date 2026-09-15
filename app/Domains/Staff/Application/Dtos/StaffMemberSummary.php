<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Domains\Staff\ValueObjects\StaffRole;

final readonly class StaffMemberSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public StaffRole $role,
    ) {}

    public static function fromEntity(StaffMember $member, AccountSnapshot $account): self
    {
        return new self(
            id: $member->id,
            name: $account->name,
            email: $account->email,
            role: $member->role(),
        );
    }
}
