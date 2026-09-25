<?php

declare(strict_types=1);

namespace App\Domains\Staff\Entities;

use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\OwnerCannotBeRemoved;
use App\Domains\Staff\Exceptions\OwnerLevelIsFixed;
use App\Domains\Staff\Exceptions\TeamInvitationNotPending;
use App\Domains\Staff\ValueObjects\AccessTransition;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;
use SensitiveParameter;

final class StaffMember
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $accountId,
        private StaffRole $role,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function registerOwner(
        string $id,
        string $businessId,
        string $accountId,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            accountId: $accountId,
            role: StaffRole::Owner,
            createdAt: $now,
        );
    }

    /**
     * @throws InvalidTeamLevel
     */
    public static function register(
        string $id,
        string $businessId,
        string $accountId,
        DateTimeImmutable $now,
        StaffRole $role = StaffRole::Member,
    ): self {
        self::ensureAssignable($role);

        return new self(
            id: $id,
            businessId: $businessId,
            accountId: $accountId,
            role: $role,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $accountId,
        StaffRole $role,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            accountId: $accountId,
            role: $role,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws OwnerLevelIsFixed
     * @throws InvalidTeamLevel
     */
    public function changeRole(StaffRole $role): AccessTransition
    {
        if ($this->role === StaffRole::Owner) {
            throw OwnerLevelIsFixed::for($this->id);
        }

        self::ensureAssignable($role);

        $transition = AccessTransition::between($this->role, $role);
        $this->role = $role;

        return $transition;
    }

    /**
     * @throws OwnerCannotBeRemoved
     */
    public function ensureRemovable(): void
    {
        if ($this->role === StaffRole::Owner) {
            throw OwnerCannotBeRemoved::for($this->id);
        }
    }

    public function hasPendingInvitation(AccountSnapshot $account): bool
    {
        return $this->role === StaffRole::Member && $account->awaitingPasswordChange;
    }

    /**
     * @throws TeamInvitationNotPending
     */
    public function ensureInvitationPending(AccountSnapshot $account): void
    {
        if (! $this->hasPendingInvitation($account)) {
            throw TeamInvitationNotPending::for($this->id);
        }
    }

    /**
     * @return list<TeamMemberInvited>
     */
    public function invitationFor(#[SensitiveParameter] ?string $temporaryPassword): array
    {
        if (! $this->role->grantsAccess()) {
            return [];
        }

        return [new TeamMemberInvited($this->id, $this->businessId, $this->accountId, $temporaryPassword)];
    }

    public function role(): StaffRole
    {
        return $this->role;
    }

    /**
     * @throws InvalidTeamLevel
     */
    private static function ensureAssignable(StaffRole $role): void
    {
        if (! $role->isAssignable()) {
            throw InvalidTeamLevel::ownerNotAssignable();
        }
    }
}
