<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\BusinessMembership;
use App\Shared\Contracts\BusinessTeamKey;
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

        abort_if($accountId === null, Response::HTTP_FORBIDDEN, __('messages.errors.no_business'));

        $available = $this->memberships->businessIdsFor((string) $accountId);

        abort_if($available === [], Response::HTTP_FORBIDDEN, __('messages.errors.no_business'));

        $requested = $request->header(self::BUSINESS_HEADER);

        if ($requested === null) {
            return $available[0];
        }

        abort_if(
            ! in_array($requested, $available, strict: true),
            Response::HTTP_FORBIDDEN,
            __('messages.errors.business_not_accessible'),
        );

        return $requested;
    }
}
