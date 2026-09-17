<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CustomerPhotoTooLarge extends DomainException implements DomainFailure
{
    public static function atBytes(int $sizeInBytes, int $maximumBytes): self
    {
        return new self("A customer photo takes up to [{$maximumBytes}] bytes, got [{$sizeInBytes}].");
    }

    public function errorCode(): string
    {
        return 'customer_photo_too_large';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
