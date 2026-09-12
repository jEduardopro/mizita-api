<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use DomainException;

/**
 * Raised when Google has not verified the address behind a sign-in attempt
 * that would otherwise create or claim an account.
 *
 * This is the account takeover guard: an unverified Google address is an
 * address nobody has proven control of, so it may never be matched against an
 * existing account nor used to register a new one.
 */
final class GoogleEmailNotVerified extends DomainException
{
    public static function forEmail(string $email): self
    {
        return new self("Google has not verified the email address [{$email}].");
    }
}
