<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ServiceImageTooLarge extends DomainException implements DomainFailure
{
    public static function atBytes(int $sizeInBytes, int $maximumBytes): self
    {
        return new self("A service image takes up to [{$maximumBytes}] bytes, got [{$sizeInBytes}].");
    }

    public function errorCode(): string
    {
        return 'service_image_too_large';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
