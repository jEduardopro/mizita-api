<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PlatformAdminAlreadyExists extends DomainException implements DomainFailure
{
    public static function withEmail(string $email): self
    {
        return new self("A platform admin with the email [{$email}] already exists.");
    }

    public function errorCode(): string
    {
        return 'platform_admin_already_exists';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
