<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;

/**
 * A person as Google describes them, once their credential has been verified.
 *
 * Immutable and self-validating, so any code holding one knows the subject is
 * present and the email is well formed - whichever entry point produced it.
 */
final readonly class GoogleIdentity
{
    public string $sub;

    public string $email;

    public function __construct(
        string $sub,
        string $email,
        public bool $emailVerified,
        public string $name,
        public ?string $avatarUrl = null,
    ) {
        $sub = trim($sub);

        // An empty subject would collide with every other empty subject already
        // stored, handing one caller somebody else's account.
        if ($sub === '') {
            throw InvalidGoogleIdToken::missingSubject();
        }

        $this->sub = $sub;
        $this->email = self::normalizeEmail($email);
    }

    private static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            throw InvalidAccountEmail::empty();
        }

        return $email;
    }
}
