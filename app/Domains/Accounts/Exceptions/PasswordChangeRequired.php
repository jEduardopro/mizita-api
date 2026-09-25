<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PasswordChangeRequired extends DomainException implements DomainFailure
{
    public static function beforeContinuing(): self
    {
        return new self('The caller must replace their temporary password before continuing.');
    }

    public function errorCode(): string
    {
        return 'password_change_required';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
