<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidProfileAbout extends DomainException implements DomainFailure
{
    public static function tooLong(int $maximumLength): self
    {
        return new self("A profile description takes up to [{$maximumLength}] characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_profile_about';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
