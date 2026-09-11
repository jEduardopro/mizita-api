<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Contracts\BusinessContext;
use App\Shared\Infrastructure\RequestBusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the business the request operates on and binds it for the rest of
 * the lifecycle. It aborts rather than binding nothing, so a tenant-scoped
 * query can never run unscoped behind a route carrying this middleware.
 */
final class SetBusinessContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessId = $request->user()?->business?->uuid;

        abort_if($businessId === null, Response::HTTP_FORBIDDEN, __('messages.errors.no_business'));

        app()->instance(BusinessContext::class, new RequestBusinessContext($businessId));

        return $next($request);
    }
}
