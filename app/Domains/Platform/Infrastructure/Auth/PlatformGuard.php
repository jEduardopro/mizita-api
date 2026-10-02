<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformAdminModel;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory;
use LogicException;

final class PlatformGuard
{
    public const NAME = 'platform';

    public const PROVIDER = 'platform_admins';

    public const OWNER_GUARD = 'web';

    public function __construct(
        private readonly Factory $auth,
    ) {}

    public function admins(): SessionGuard
    {
        return $this->sessionGuard(self::NAME);
    }

    public function owners(): SessionGuard
    {
        return $this->sessionGuard(self::OWNER_GUARD);
    }

    public function signedInAdmin(): ?PlatformAdminModel
    {
        $admin = $this->admins()->user();

        return $admin instanceof PlatformAdminModel ? $admin : null;
    }

    private function sessionGuard(string $name): SessionGuard
    {
        $guard = $this->auth->guard($name);

        if (! $guard instanceof SessionGuard) {
            throw new LogicException("The [{$name}] guard must keep its user in the session.");
        }

        return $guard;
    }
}
