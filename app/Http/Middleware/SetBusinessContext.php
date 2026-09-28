<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\BusinessAccessDenied;
use App\Http\Exceptions\TeamAccessPaused;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\BusinessMembership;
use App\Shared\Contracts\BusinessTeamKey;
use App\Shared\Contracts\PausedBusinessAccess;
use App\Shared\Infrastructure\RequestBusinessContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class SetBusinessContext
{
    private const BUSINESS_HEADER = 'X-Business';

    public function __construct(
        private readonly BusinessMembership $memberships,
        private readonly BusinessTeamKey $teamKeys,
        private readonly PausedBusinessAccess $pausedAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $businessId = $this->resolveBusinessId($request);

        app()->instance(BusinessContext::class, new RequestBusinessContext($businessId));

        app(PermissionRegistrar::class)->setPermissionsTeamId(
            $this->teamKeys->teamKeyFor($businessId),
        );

        return $next($request);
    }

    private function resolveBusinessId(Request $request): string
    {
        $accountId = $request->user()?->uuid;

        if ($accountId === null) {
            throw BusinessAccessDenied::accountHasNoBusiness();
        }

        $available = $this->memberships->businessIdsFor((string) $accountId);

        if ($available === []) {
            $this->guardAgainstEveryMembershipPaused((string) $accountId);

            throw BusinessAccessDenied::accountHasNoBusiness();
        }

        $requested = $request->header(self::BUSINESS_HEADER);

        if ($requested === null) {
            return $available[0];
        }

        if (! in_array($requested, $available, strict: true)) {
            $this->guardAgainstPausedBusiness((string) $accountId, $requested);

            throw BusinessAccessDenied::businessNotAccessible($requested);
        }

        return $requested;
    }

    private function guardAgainstEveryMembershipPaused(string $accountId): void
    {
        if ($this->pausedAccess->pausedBusinessIdsFor($accountId) !== []) {
            throw TeamAccessPaused::forEveryMembership();
        }
    }

    private function guardAgainstPausedBusiness(string $accountId, string $businessId): void
    {
        if (in_array($businessId, $this->pausedAccess->pausedBusinessIdsFor($accountId), strict: true)) {
            throw TeamAccessPaused::forBusiness($businessId);
        }
    }
}
