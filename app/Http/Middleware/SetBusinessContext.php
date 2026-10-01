<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\BusinessAccessDenied;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\BusinessTeamKey;
use App\Shared\Contracts\CurrentBusinessResolver;
use App\Shared\Infrastructure\RequestBusinessContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class SetBusinessContext
{
    public const BUSINESS_HEADER = 'X-Business';

    public function __construct(
        private readonly CurrentBusinessResolver $currentBusiness,
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

        if ($accountId === null) {
            throw BusinessAccessDenied::accountHasNoBusiness();
        }

        return $this->currentBusiness->resolveFor(
            (string) $accountId,
            $request->header(self::BUSINESS_HEADER),
        );
    }
}
