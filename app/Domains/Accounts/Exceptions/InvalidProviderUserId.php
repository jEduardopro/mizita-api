<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

/**
 * An empty provider subject is never a valid identity: it would match every
 * other empty subject already stored, handing one caller somebody else's
 * account. The guard lives on the entity so it holds on every path, not only
 * the ones that happen to build a GoogleIdentity first.
 */
final class InvalidProviderUserId extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A provider user id cannot be empty.');
    }

    public function errorCode(): string
    {
        return 'invalid_provider_user_id';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
