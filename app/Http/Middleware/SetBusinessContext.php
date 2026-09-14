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

/**
 * Aborts rather than binding nothing, so a tenant-scoped query can never run
 * unscoped behind a route carrying this middleware. The tenant comes from the
 * caller's memberships, never from a column on the user and never from the
 * request body.
 *
 * This class imports only App\Shared\Contracts\* and must never import
 * App\Domains\Staff\* - or any other domain. It sits outside App\Domains, so the
 * architecture tests do not police its imports and the discipline has to be
 * written down instead.
 */
final class SetBusinessContext
{
    /** Absent, the first membership wins. */
    private const BUSINESS_HEADER = 'X-Business';

    public function __construct(
        private readonly BusinessMembership $memberships,
        private readonly BusinessTeamKey $teamKeys,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $businessId = $this->resolveBusinessId($request);

        app()->instance(BusinessContext::class, new RequestBusinessContext($businessId));

        // Spatie scopes every role and permission check by the current team, and
        // that team defaults to null. Leaving it unset would make can() and
        // hasRole() read a team nobody holds a role in, so every check would
        // deny - silently, and identically to a genuine lack of permission.
        app(PermissionRegistrar::class)->setPermissionsTeamId(
            $this->teamKeys->teamKeyFor($businessId),
        );

        return $next($request);
    }

    /**
     * 403 rather than 404 for an unknown or foreign business: the route is
     * authenticated, and which businesses exist is not a secret being kept from
     * a signed-in user. The "404, not 403" rule guards public slugs, where a 403
     * would confirm the business is real to anyone who guesses it.
     */
    private function resolveBusinessId(Request $request): string
    {
        $accountId = $request->user()?->uuid;

        abort_if($accountId === null, Response::HTTP_FORBIDDEN, __('messages.errors.no_business'));

        $available = $this->memberships->businessIdsFor((string) $accountId);

        abort_if($available === [], Response::HTTP_FORBIDDEN, __('messages.errors.no_business'));

        $requested = $request->header(self::BUSINESS_HEADER);

        if ($requested === null) {
            // Owner membership first, then by membership age: the head of the
            // list is the business the caller registered.
            return $available[0];
        }

        // Its own message, not no_business: this caller does belong to a
        // business, just not the one asked for, and telling them otherwise
        // sends them looking for a problem they do not have.
        abort_if(
            ! in_array($requested, $available, strict: true),
            Response::HTTP_FORBIDDEN,
            __('messages.errors.business_not_accessible'),
        );

        return $requested;
    }
}
