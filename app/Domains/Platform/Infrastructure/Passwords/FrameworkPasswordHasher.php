<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Passwords;

use App\Domains\Platform\Contracts\PasswordHasher;
use Illuminate\Contracts\Hashing\Hasher;

final class FrameworkPasswordHasher implements PasswordHasher
{
    public function __construct(
        private readonly Hasher $hasher,
    ) {}

    public function hash(string $password): string
    {
        return $this->hasher->make($password);
    }
}
