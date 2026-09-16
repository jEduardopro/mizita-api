<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnknownState extends DomainException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("State [{$id}] is not on record.");
    }

    public function errorCode(): string
    {
        return 'unknown_state';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
