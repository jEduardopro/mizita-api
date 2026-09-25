<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Passwords;

use App\Domains\Accounts\Contracts\PasswordVerifier;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;

final class FrameworkPasswordVerifier implements PasswordVerifier
{
    public function __construct(
        private readonly Hasher $hasher,
    ) {}

    public function matches(string $accountId, string $plaintext): bool
    {
        $hashedPassword = User::withTrashed()->where('uuid', $accountId)->value('password');

        if (! is_string($hashedPassword) || $plaintext === '') {
            return false;
        }

        return $this->hasher->check($plaintext, $hashedPassword);
    }
}
