<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class GoogleEmailNotVerified extends DomainException implements DomainFailure
{
    public static function forEmail(string $email): self
    {
        return new self("Google has not verified the email address [{$email}].");
    }

    public function errorCode(): string
    {
        return 'google_email_not_verified';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
