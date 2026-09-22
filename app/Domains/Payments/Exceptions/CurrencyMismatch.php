<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CurrencyMismatch extends DomainException implements DomainFailure
{
    public static function between(string $expected, string $actual): self
    {
        return new self("Expected currency [{$expected}] but received [{$actual}].");
    }

    public function errorCode(): string
    {
        return 'currency_mismatch';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
