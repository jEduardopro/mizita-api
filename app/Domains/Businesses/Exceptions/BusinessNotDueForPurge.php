<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DateTimeImmutable;
use DomainException;

final class BusinessNotDueForPurge extends DomainException implements DomainFailure
{
    public static function until(string $id, DateTimeImmutable $purgeDueAt): self
    {
        return new self("Business [{$id}] cannot be purged before [{$purgeDueAt->format(DATE_ATOM)}].");
    }

    public function errorCode(): string
    {
        return 'business_not_due_for_purge';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
