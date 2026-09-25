<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Passwords;

use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\ValueObjects\TemporaryPassword;
use Illuminate\Support\Str;

final class RandomTemporaryPasswordGenerator implements TemporaryPasswordGenerator
{
    private const LENGTH = 20;

    public function generate(): TemporaryPassword
    {
        return TemporaryPassword::fromString(Str::password(self::LENGTH, symbols: false));
    }
}
