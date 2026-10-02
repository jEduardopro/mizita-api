<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

interface AuthenticatorApp
{
    public function confirms(string $secret, string $code): bool;
}
