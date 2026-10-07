<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;

final readonly class NotifiedStaffMemberData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromStaffMember(NotifiedStaffMember $staffMember): self
    {
        return new self(
            id: $staffMember->staffMemberId,
            name: $staffMember->name,
        );
    }
}
