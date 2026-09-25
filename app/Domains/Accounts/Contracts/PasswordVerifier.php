<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

interface PasswordVerifier
{
    public function matches(string $accountId, string $plaintext): bool;
}
