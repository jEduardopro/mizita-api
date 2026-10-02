<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\TwoFactor;

use App\Domains\Platform\Contracts\AuthenticatorApp;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

final class FortifyAuthenticatorApp implements AuthenticatorApp
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    public function confirms(string $secret, string $code): bool
    {
        if ($secret === '' || $code === '') {
            return false;
        }

        return $this->provider->verify($secret, $code);
    }
}
