<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidServicePrice extends DomainException implements DomainFailure
{
    public static function negative(): self
    {
        return new self('A service price cannot be negative.');
    }

    public static function malformed(): self
    {
        return new self('A service price is an amount with at most two decimals.');
    }

    public function errorCode(): string
    {
        return 'invalid_service_price';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
