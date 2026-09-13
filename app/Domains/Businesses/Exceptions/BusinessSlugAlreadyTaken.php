<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

/**
 * The web address the name was turned into is already in use.
 *
 * Reaching a caller means two signups computed the same suffix from the same
 * empty read and the database rejected the loser. Onboarding does not retry:
 * it is a deliberate one-shot human action, and the honest answer to the loser
 * is that the name is gone.
 */
final class BusinessSlugAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $value, ?Throwable $previous = null): self
    {
        return new self("A business with slug [{$value}] already exists.", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'business_slug_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
