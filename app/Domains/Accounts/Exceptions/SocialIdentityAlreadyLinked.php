<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class SocialIdentityAlreadyLinked extends DomainException implements DomainFailure
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

    public function errorCode(): string
    {
        return 'social_identity_already_linked';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
