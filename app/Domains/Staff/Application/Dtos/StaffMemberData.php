<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;

/**
 * Output boundary. Entities never leave the application layer, so use cases
 * return this instead.
 *
 * Both identifiers are uuids, as everywhere above Infrastructure.
 */
final readonly class StaffMemberData
{
    public function __construct(
        public string $id,
        public string $businessId,
        public string $accountId,
        public StaffRole $role,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(StaffMember $member): self
    {
        return new self(
            id: $member->id,
            businessId: $member->businessId,
            accountId: $member->accountId,
            role: $member->role(),
            createdAt: $member->createdAt,
        );
    }
}
