<?php

declare(strict_types=1);

namespace App\Domains\Staff\Entities;

use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;

/**
 * A person's membership of one business, and the role it grants them.
 *
 * This row is what makes an account a business user: no membership means the
 * holder is an end customer. That is why it carries the public uuids of both
 * neighbours - the int keys the schema joins on stay in the repository adapter.
 *
 * Domain entity: plain PHP, no framework. It owns the business rules and
 * protects its own invariants. Persistence is handled by the repository
 * adapter through StaffMemberMapper.
 */
final class StaffMember
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $accountId,
        private StaffRole $role,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * Makes the account that registered a business its owner.
     *
     * A named constructor per role rather than one create() taking a StaffRole:
     * the owner membership is a distinct moment in a business's life - written
     * once by signup, and capped platform-wide by a partial unique index - and
     * the call site should read as what it is.
     */
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
     * Adds somebody to a business's team, with no ownership.
     */
    public static function register(
        string $id,
        string $businessId,
        string $accountId,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            accountId: $accountId,
            role: StaffRole::Member,
            createdAt: $now,
        );
    }

    /**
     * Rehydrates a membership from storage. Skips creation-time rules by
     * design: the data was already valid when it was written.
     */
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

    public function role(): StaffRole
    {
        return $this->role;
    }
}
