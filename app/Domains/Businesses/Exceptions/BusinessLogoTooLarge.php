<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BusinessLogoTooLarge extends DomainException implements DomainFailure
{
    public static function atBytes(int $sizeInBytes, int $maximumBytes): self
    {
        return new self("A business logo takes up to [{$maximumBytes}] bytes, got [{$sizeInBytes}].");
    }

    public function errorCode(): string
    {
        return 'business_logo_too_large';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
