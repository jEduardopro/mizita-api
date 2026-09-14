<?php

declare(strict_types=1);

namespace App\Domains\Staff\Entities;

use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;

/**
 * This row is what makes an account a business user: no membership means the
 * holder is an end customer. It carries the public uuids of both neighbours -
 * the int keys the schema joins on stay in the repository adapter.
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
     * A named constructor per role rather than one create() taking a StaffRole:
     * the owner membership is a distinct moment in a business's life, written
     * once by signup and capped platform-wide by a partial unique index.
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

    /** Skips creation-time rules by design: the data was already valid when written. */
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
