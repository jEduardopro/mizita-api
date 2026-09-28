<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Contracts\BusinessMembership;
use App\Shared\Contracts\PausedBusinessAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireBusinessMembership
{
    public const TEAM_ACCESS_PAUSED_ROUTE = 'team-access.paused';

    public const ONBOARDING_ROUTE = 'onboarding';

    public function __construct(
        private readonly BusinessMembership $memberships,
        private readonly PausedBusinessAccess $pausedAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accountId = (string) $request->user()?->uuid;

        if ($this->memberships->businessIdsFor($accountId) !== []) {
            return $next($request);
        }

        if ($this->pausedAccess->pausedBusinessIdsFor($accountId) !== []) {
            return redirect()->route(self::TEAM_ACCESS_PAUSED_ROUTE);
        }

        return redirect()->route(self::ONBOARDING_ROUTE);
    }
}
