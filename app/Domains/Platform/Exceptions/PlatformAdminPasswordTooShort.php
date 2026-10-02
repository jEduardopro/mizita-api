<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PlatformAdminPasswordTooShort extends DomainException implements DomainFailure
{
    public static function below(int $minimumLength): self
    {
        return new self("A platform admin password takes at least [{$minimumLength}] characters.");
    }

    public function errorCode(): string
    {
        return 'platform_admin_password_too_short';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
