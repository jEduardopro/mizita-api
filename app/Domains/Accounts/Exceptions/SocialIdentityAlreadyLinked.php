<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Domains\Accounts\ValueObjects\SocialProvider;
use DomainException;
use Throwable;

/**
 * Another request linked this provider user first.
 *
 * This is the storage layer's uniqueness rule reaching the domain in domain
 * terms. It says nothing about what to do next: a caller racing itself should
 * adopt the winner's link, not report a conflict.
 */
final class SocialIdentityAlreadyLinked extends DomainException
{
    public static function forProviderUser(
        SocialProvider $provider,
        string $providerUserId,
        ?Throwable $previous = null,
    ): self {
        return new self(
            "[{$provider->value}] user [{$providerUserId}] is already linked to an account.",
            previous: $previous,
        );
    }
}
