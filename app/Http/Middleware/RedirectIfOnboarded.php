<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Contracts\BusinessMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectIfOnboarded
{
    private const DASHBOARD_ROUTE = 'dashboard';

    public function __construct(
        private readonly BusinessMembership $memberships,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accountId = (string) $request->user()?->uuid;

        if ($this->memberships->businessIdsFor($accountId) !== []) {
            return redirect()->route(self::DASHBOARD_ROUTE);
        }

        return $next($request);
    }
}
