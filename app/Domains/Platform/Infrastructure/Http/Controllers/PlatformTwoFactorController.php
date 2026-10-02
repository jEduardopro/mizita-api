<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Controllers;

use App\Domains\Platform\Infrastructure\Auth\PendingPlatformLogin;
use App\Domains\Platform\Infrastructure\Auth\PlatformSecondFactor;
use App\Domains\Platform\Infrastructure\Auth\PlatformSignIn;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Domains\Platform\Infrastructure\Http\Requests\PlatformTwoFactorRequest;
use App\Http\Controllers\Controller;
use App\Shared\Contracts\Clock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformTwoFactorController extends Controller
{
    private const PAGE = 'platform/auth/two-factor';

    private const INVALID_CODE_MESSAGE = 'platform.auth.invalid_two_factor_code';

    public function show(PendingPlatformLogin $pendingLogin, Clock $clock): RedirectResponse|Response
    {
        if ($pendingLogin->pendingAdminId($clock->now()) === null) {
            return redirect()->route(PlatformRoutes::LOGIN);
        }

        return Inertia::render(self::PAGE);
    }

    /**
     * @throws ValidationException
     */
    public function store(
        PlatformTwoFactorRequest $request,
        PendingPlatformLogin $pendingLogin,
        PlatformSecondFactor $secondFactor,
        PlatformSignIn $signIn,
        Clock $clock,
    ): RedirectResponse {
        $now = $clock->now();
        $adminId = $pendingLogin->pendingAdminId($now);

        if ($adminId === null) {
            return redirect()->route(PlatformRoutes::LOGIN);
        }

        if (! $secondFactor->confirms($adminId, $request->code())) {
            throw ValidationException::withMessages([
                PlatformTwoFactorRequest::CODE => (string) __(self::INVALID_CODE_MESSAGE),
            ]);
        }

        $pendingLogin->forget();

        $signIn->signIn($adminId, $now);

        return redirect()->route(PlatformRoutes::BUSINESSES);
    }
}
