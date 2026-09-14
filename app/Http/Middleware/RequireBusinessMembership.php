<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Contracts\BusinessMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after `auth`, which guarantees there is a user to ask about. A redirect
 * rather than the 403 SetBusinessContext raises, because this guards a page
 * visit: a fresh account with no business has done nothing wrong.
 *
 * Like SetBusinessContext, it reaches memberships only through the shared port
 * and must never import App\Domains\Staff\*.
 */
final class RequireBusinessMembership
{
    public function __construct(
        private readonly BusinessMembership $memberships,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accountId = $request->user()?->uuid;

        if ($this->memberships->businessIdsFor((string) $accountId) === []) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
