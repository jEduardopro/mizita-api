<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ProfilePhotoTooLarge extends DomainException implements DomainFailure
{
    public static function atBytes(int $sizeInBytes, int $maximumBytes): self
    {
        return new self("A profile photo takes up to [{$maximumBytes}] bytes, got [{$sizeInBytes}].");
    }

    public function errorCode(): string
    {
        return 'profile_photo_too_large';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
