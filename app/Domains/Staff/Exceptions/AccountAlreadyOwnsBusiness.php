<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

/**
 * This account already holds an owner membership, so it cannot own a second
 * business: a user owns one business or none.
 *
 * Raised in two places for one rule. RegisterBusinessOwner checks first and
 * turns the ordinary case into a clean refusal; the partial unique index on
 * (account_id) where role = 'owner' rejects the racing case, and the repository
 * translates that rejection into this same exception. The guard is the
 * courtesy, the index is the guarantee.
 */
final class AccountAlreadyOwnsBusiness extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId, ?Throwable $previous = null): self
    {
        return new self("Account [{$accountId}] already owns a business.", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'owner_already_has_business';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
