<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformAdminModel;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

final class PlatformSignIn
{
    public function __construct(
        private readonly PlatformGuard $guards,
        private readonly PlatformActivity $activity,
        private readonly Session $session,
    ) {}

    public function signIn(string $adminId, DateTimeImmutable $now): void
    {
        $admin = PlatformAdminModel::query()->where('uuid', $adminId)->firstOrFail();

        $this->guards->admins()->login($admin);

        $this->session->regenerate();

        $admin->forceFill(['last_login_at' => $now])->save();

        $this->activity->touch($now);
    }
}
