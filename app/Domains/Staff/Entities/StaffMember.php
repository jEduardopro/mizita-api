<?php

declare(strict_types=1);

namespace App\Domains\Staff\Entities;

use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;

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
