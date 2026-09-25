<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

interface PasswordHasher
{
    public function hash(string $plaintext): string;
}
