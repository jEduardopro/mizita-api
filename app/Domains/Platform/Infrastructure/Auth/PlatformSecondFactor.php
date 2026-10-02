<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use App\Domains\Platform\Contracts\AuthenticatorApp;
use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformAdminModel;

final class PlatformSecondFactor
{
    public function __construct(
        private readonly AuthenticatorApp $authenticatorApp,
    ) {}

    public function confirms(string $adminId, string $code): bool
    {
        $admin = PlatformAdminModel::query()
            ->where('uuid', $adminId)
            ->whereNotNull('two_factor_confirmed_at')
            ->first();

        if ($admin === null) {
            return false;
        }

        return $this->authenticatorApp->confirms((string) $admin->two_factor_secret, $code);
    }
}
