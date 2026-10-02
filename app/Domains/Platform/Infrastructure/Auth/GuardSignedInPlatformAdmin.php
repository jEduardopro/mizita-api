<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use App\Shared\Contracts\SignedInPlatformAdmin;

final class GuardSignedInPlatformAdmin implements SignedInPlatformAdmin
{
    public function __construct(
        private readonly PlatformGuard $guards,
    ) {}

    public function describe(): ?array
    {
        $admin = $this->guards->signedInAdmin();

        if ($admin === null) {
            return null;
        }

        return [
            'name' => (string) $admin->name,
            'email' => (string) $admin->email,
        ];
    }
}
