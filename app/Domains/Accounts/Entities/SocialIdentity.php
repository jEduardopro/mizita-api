<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Entities;

use App\Domains\Accounts\Exceptions\InvalidProviderUserId;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use DateTimeImmutable;

/**
 * The link between an account and one identity provider's user.
 *
 * providerUserId is the provider's stable subject - Google's "sub" - and never
 * an email address: an email can be reassigned, a subject cannot.
 */
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

        // An empty subject would match every other empty subject on the next
        // lookup. Guarding here rather than only where the identity is parsed
        // means no caller can route around it.
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

    /** Skips creation-time rules by design: the data was already valid when written. */
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
