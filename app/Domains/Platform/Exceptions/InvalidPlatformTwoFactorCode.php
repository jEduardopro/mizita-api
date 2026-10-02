<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPlatformTwoFactorCode extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('An authenticator app code is six digits.');
    }

    public static function notConfirmedBy(string $email): self
    {
        return new self("The authenticator app code did not confirm the enrollment of [{$email}].");
    }

    public function errorCode(): string
    {
        return 'invalid_platform_two_factor_code';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
