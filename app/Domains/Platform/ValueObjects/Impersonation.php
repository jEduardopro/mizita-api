<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

use DateInterval;
use DateTimeImmutable;

final readonly class Impersonation
{
    public const DURATION_MINUTES = 60;

    private function __construct(
        public string $adminId,
        public string $accountId,
        public string $businessId,
        public string $businessName,
        public string $ownerName,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    public static function begin(string $adminId, BusinessOwnerAccount $owner, DateTimeImmutable $now): self
    {
        return new self(
            adminId: $adminId,
            accountId: $owner->accountId,
            businessId: $owner->businessId,
            businessName: $owner->businessName,
            ownerName: $owner->ownerName,
            startedAt: $now,
            expiresAt: $now->add(new DateInterval('PT'.self::DURATION_MINUTES.'M')),
        );
    }

    public static function restore(
        string $adminId,
        string $accountId,
        string $businessId,
        string $businessName,
        string $ownerName,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $expiresAt,
    ): self {
        return new self($adminId, $accountId, $businessId, $businessName, $ownerName, $startedAt, $expiresAt);
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isStillValidFor(?string $signedInAdminId, ?string $signedInAccountId, DateTimeImmutable $now): bool
    {
        return $signedInAdminId === $this->adminId
            && $signedInAccountId === $this->accountId
            && ! $this->isExpiredAt($now);
    }
}
