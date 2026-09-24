<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class InvalidProfileName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A profile name cannot be empty.');
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("A profile name takes up to [{$maximumLength}] characters.");
    }

    public static function rejectedByAccount(Throwable $previous): self
    {
        return new self('The account refused that name.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'invalid_profile_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
