<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Entities;

use App\Domains\Accounts\Exceptions\InvalidProviderUserId;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use DateTimeImmutable;

final class SocialIdentity
{
    private function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly SocialProvider $provider,
        public readonly string $providerUserId,
        public readonly DateTimeImmutable $linkedAt,
    ) {}

    public static function link(
        string $id,
        string $accountId,
        SocialProvider $provider,
        string $providerUserId,
        DateTimeImmutable $now,
    ): self {
        $providerUserId = trim($providerUserId);

        if ($providerUserId === '') {
            throw InvalidProviderUserId::empty();
        }

        return new self(
            id: $id,
            accountId: $accountId,
            provider: $provider,
            providerUserId: $providerUserId,
            linkedAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $accountId,
        SocialProvider $provider,
        string $providerUserId,
        DateTimeImmutable $linkedAt,
    ): self {
        return new self(
            id: $id,
            accountId: $accountId,
            provider: $provider,
            providerUserId: $providerUserId,
            linkedAt: $linkedAt,
        );
    }
}
