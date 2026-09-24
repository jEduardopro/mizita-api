<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidProfileJobTitle extends DomainException implements DomainFailure
{
    public static function tooLong(int $maximumLength): self
    {
        return new self("A job title takes up to [{$maximumLength}] characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_profile_job_title';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
