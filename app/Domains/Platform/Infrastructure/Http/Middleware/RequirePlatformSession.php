<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Middleware;

use App\Domains\Platform\Exceptions\PlatformSessionExpired;
use App\Domains\Platform\Infrastructure\Auth\PlatformActivity;
use App\Domains\Platform\Infrastructure\Auth\PlatformGuard;
use App\Domains\Platform\Infrastructure\Auth\PlatformSignOut;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Http\Responses\JsonFailureRendering;
use App\Shared\Contracts\Clock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlatformSession
{
    public function __construct(
        private readonly PlatformGuard $guards,
        private readonly PlatformActivity $activity,
        private readonly PlatformSignOut $signOut,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws PlatformSessionExpired
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->guards->signedInAdmin() === null) {
            return $this->sendToLogin($request);
        }

        $now = $this->clock->now();

        if ($this->activity->isIdleAt($now)) {
            $this->signOut->signOut();

            return $this->sendToLogin($request);
        }

        $this->activity->touch($now);

        return $next($request);
    }

    /**
     * @throws PlatformSessionExpired
     */
    private function sendToLogin(Request $request): Response
    {
        if (JsonFailureRendering::appliesTo($request)) {
            throw PlatformSessionExpired::signedOut();
        }

        return redirect()->route(PlatformRoutes::LOGIN);
    }
}
