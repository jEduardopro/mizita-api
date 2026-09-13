<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

/**
 * This account already owns a business, so it cannot onboard a second one.
 *
 * The rule belongs to Staff, where ownership is a membership row. This class is
 * how that refusal reaches Businesses in its own vocabulary: the gateway
 * translates the neighbour's exception into this one, so nothing above
 * Infrastructure ever has to recognise a Staff class.
 */
final class OwnerAlreadyHasBusiness extends DomainException implements DomainFailure
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
