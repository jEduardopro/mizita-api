<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http;

use Illuminate\Http\Request;

final class PlatformRoutes
{
    public const PATH = 'mizita-admin';

    public const HOME = 'platform.home';

    public const LOGIN = 'platform.login';

    public const LOGIN_STORE = 'platform.login.store';

    public const TWO_FACTOR = 'platform.two-factor';

    public const TWO_FACTOR_STORE = 'platform.two-factor.store';

    public const LOGOUT = 'platform.logout';

    public const BUSINESSES = 'platform.businesses';

    public const IMPERSONATION_START = 'platform.impersonation.start';

    public const IMPERSONATION_STOP = 'platform.impersonation.stop';

    public const SESSION_MIDDLEWARE = 'platform.session';

    public const LOGIN_LIMITER = 'platform-login';

    public const TWO_FACTOR_LIMITER = 'platform-two-factor';

    public static function owns(Request $request): bool
    {
        return $request->is(self::PATH, self::PATH.'/*');
    }
}
