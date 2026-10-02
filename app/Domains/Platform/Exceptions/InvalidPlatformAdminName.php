<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPlatformAdminName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A platform admin name cannot be empty.');
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("A platform admin name takes up to [{$maximumLength}] characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_platform_admin_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
