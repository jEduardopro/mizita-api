<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPlatformAdminEmail extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A platform admin email cannot be empty.');
    }

    public static function malformed(string $email): self
    {
        return new self("[{$email}] is not a valid email address.");
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("A platform admin email takes up to [{$maximumLength}] characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_platform_admin_email';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
