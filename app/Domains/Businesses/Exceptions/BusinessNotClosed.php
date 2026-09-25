<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BusinessNotClosed extends DomainException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Business [{$id}] is not closed.");
    }

    public static function byAccount(string $id, string $accountId): self
    {
        return new self("Business [{$id}] was not closed by account [{$accountId}].");
    }

    public function errorCode(): string
    {
        return 'business_not_closed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
