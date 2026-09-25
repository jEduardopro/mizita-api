<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Passwords;

use App\Domains\Accounts\Contracts\PasswordHasher;
use Illuminate\Contracts\Hashing\Hasher;

final class FrameworkPasswordHasher implements PasswordHasher
{
    public function __construct(
        private readonly Hasher $hasher,
    ) {}

    public function hash(string $plaintext): string
    {
        return $this->hasher->make($plaintext);
    }
}
