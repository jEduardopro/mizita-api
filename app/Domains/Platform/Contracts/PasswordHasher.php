<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

interface PasswordHasher
{
    public function hash(string $password): string;
}
