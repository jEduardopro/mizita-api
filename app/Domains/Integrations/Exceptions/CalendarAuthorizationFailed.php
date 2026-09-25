<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class CalendarAuthorizationFailed extends DomainException implements DomainFailure
{
    public static function providerError(string $error): self
    {
        return new self("The calendar provider answered the authorization with [{$error}].");
    }

    public static function missingCode(): self
    {
        return new self('The calendar authorization carried neither a code nor an error.');
    }

    public static function exchangeFailed(?Throwable $previous = null): self
    {
        return new self('The calendar authorization code could not be exchanged.', 0, $previous);
    }

    public static function missingRefreshToken(): self
    {
        return new self('The calendar provider granted no refresh token.');
    }

    public static function missingAccountEmail(): self
    {
        return new self('The calendar provider did not disclose the account email.');
    }

    public static function calendarNotProvisioned(?Throwable $previous = null): self
    {
        return new self('The dedicated calendar could not be created.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'calendar_authorization_failed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
