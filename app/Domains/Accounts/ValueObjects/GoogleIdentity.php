<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;

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
