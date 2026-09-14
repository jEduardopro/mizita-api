<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Contracts\BusinessMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror of RequireBusinessMembership. Two guards rather than one taking a
 * flag, so each route declares what it requires and neither grows a branch.
 *
 * Like SetBusinessContext, it reaches memberships only through the shared port
 * and must never import App\Domains\Staff\*.
 */
final class RedirectIfOnboarded
{
    public function __construct(
        private readonly BusinessMembership $memberships,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accountId = $request->user()?->uuid;

        if ($this->memberships->businessIdsFor((string) $accountId) !== []) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
