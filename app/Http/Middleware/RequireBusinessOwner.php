<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Contracts\BusinessAuthorization;
use App\Shared\Contracts\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireBusinessOwner
{
    private const OWNER_ROLE = 'owner';

    private const CALENDAR_ROUTE = 'calendar';

    public function __construct(
        private readonly BusinessContext $business,
        private readonly BusinessAuthorization $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accountId = $request->user()?->uuid;

        if ($accountId === null || ! $this->ownsCurrentBusiness((string) $accountId)) {
            return redirect()->route(self::CALENDAR_ROUTE);
        }

        return $next($request);
    }

    private function ownsCurrentBusiness(string $accountId): bool
    {
        $roles = $this->authorization->grantsFor($accountId, $this->business->currentBusinessId())['roles'];

        return in_array(self::OWNER_ROLE, $roles, strict: true);
    }
}
