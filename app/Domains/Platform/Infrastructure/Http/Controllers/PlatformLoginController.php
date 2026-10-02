<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Controllers;

use App\Domains\Platform\Infrastructure\Auth\PendingPlatformLogin;
use App\Domains\Platform\Infrastructure\Auth\PlatformCredentials;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Domains\Platform\Infrastructure\Http\Requests\PlatformLoginRequest;
use App\Http\Controllers\Controller;
use App\Shared\Contracts\Clock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformLoginController extends Controller
{
    private const PAGE = 'platform/auth/login';

    private const INVALID_CREDENTIALS_MESSAGE = 'platform.auth.invalid_credentials';

    public function show(): Response
    {
        return Inertia::render(self::PAGE);
    }

    /**
     * @throws ValidationException
     */
    public function store(
        PlatformLoginRequest $request,
        PlatformCredentials $credentials,
        PendingPlatformLogin $pendingLogin,
        Clock $clock,
    ): RedirectResponse {
        $adminId = $credentials->adminIdMatching($request->email(), $request->password());

        if ($adminId === null) {
            throw ValidationException::withMessages([
                PlatformLoginRequest::EMAIL => (string) __(self::INVALID_CREDENTIALS_MESSAGE),
            ]);
        }

        $pendingLogin->remember($adminId, $clock->now());

        return redirect()->route(PlatformRoutes::TWO_FACTOR);
    }
}
