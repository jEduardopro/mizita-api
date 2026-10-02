<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use App\Domains\Platform\Application\UseCases\StopImpersonation;

final class PlatformSignOut
{
    public function __construct(
        private readonly StopImpersonation $stopImpersonation,
        private readonly PlatformGuard $guards,
        private readonly PlatformActivity $activity,
    ) {}

    public function signOut(): void
    {
        $this->stopImpersonation->handle();

        $this->guards->admins()->logout();

        $this->activity->forget();
    }
}
