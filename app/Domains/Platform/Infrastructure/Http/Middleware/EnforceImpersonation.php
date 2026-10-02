<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Middleware;

use App\Domains\Platform\Application\UseCases\StopImpersonation;
use App\Domains\Platform\Exceptions\ImpersonationEnded;
use App\Domains\Platform\Exceptions\PlatformSessionExpired;
use App\Domains\Platform\Infrastructure\Auth\PlatformActivity;
use App\Domains\Platform\Infrastructure\Auth\PlatformGuard;
use App\Domains\Platform\Infrastructure\Auth\PlatformSignOut;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Domains\Platform\Infrastructure\Impersonation\SensitiveRoutes;
use App\Domains\Platform\Infrastructure\Impersonation\SessionImpersonationSession;
use App\Http\Exceptions\PermissionDenied;
use App\Http\Responses\FailurePayload;
use App\Http\Responses\JsonFailureRendering;
use App\Models\User;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnforceImpersonation
{
    private const FORTIFY_LOGOUT_ROUTE = 'logout';

    private const FLASH_ERROR_KEY = 'error';

    public function __construct(
        private readonly SessionImpersonationSession $impersonations,
        private readonly StopImpersonation $stopImpersonation,
        private readonly PlatformSignOut $signOut,
        private readonly PlatformActivity $activity,
        private readonly PlatformGuard $guards,
        private readonly SensitiveRoutes $sensitiveRoutes,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws ImpersonationEnded
     * @throws PlatformSessionExpired
     * @throws PermissionDenied
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonations->isPresent()) {
            return $next($request);
        }

        $now = $this->clock->now();

        if ($this->activity->isIdleAt($now)) {
            $this->signOut->signOut();

            return $this->sendToPlatformLogin($request, PlatformSessionExpired::signedOut());
        }

        if (! $this->isStillValidAt($now)) {
            $this->stopImpersonation->handle();

            return $this->sendToPlatformLogin($request, ImpersonationEnded::noLongerValid());
        }

        $this->activity->touch($now);

        if ($this->isOwnerLogout($request)) {
            $this->stopImpersonation->handle();

            return redirect()->route(PlatformRoutes::BUSINESSES);
        }

        if ($this->sensitiveRoutes->matches($request)) {
            return $this->refuse($request);
        }

        return $next($request);
    }

    private function isStillValidAt(DateTimeImmutable $now): bool
    {
        $impersonation = $this->impersonations->current();

        if ($impersonation === null) {
            return false;
        }

        return $impersonation->isStillValidFor(
            $this->signedInAdminId(),
            $this->signedInOwnerId(),
            $now,
        );
    }

    private function signedInAdminId(): ?string
    {
        $admin = $this->guards->signedInAdmin();

        return $admin === null ? null : (string) $admin->uuid;
    }

    private function signedInOwnerId(): ?string
    {
        $owner = $this->guards->owners()->user();

        return $owner instanceof User ? (string) $owner->uuid : null;
    }

    private function isOwnerLogout(Request $request): bool
    {
        return $request->isMethod(Request::METHOD_POST) && $request->routeIs(self::FORTIFY_LOGOUT_ROUTE);
    }

    /**
     * @throws DomainFailure
     */
    private function sendToPlatformLogin(Request $request, DomainFailure&Throwable $reason): Response
    {
        if (JsonFailureRendering::appliesTo($request)) {
            throw $reason;
        }

        return redirect()->route(PlatformRoutes::LOGIN);
    }

    /**
     * @throws PermissionDenied
     */
    private function refuse(Request $request): Response
    {
        $denied = PermissionDenied::forCurrentBusiness();

        if (JsonFailureRendering::appliesTo($request)) {
            throw $denied;
        }

        return redirect()->back()->with(self::FLASH_ERROR_KEY, FailurePayload::messageFor($denied->errorCode()));
    }
}
