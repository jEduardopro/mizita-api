<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\PermissionDenied;
use App\Shared\Contracts\BusinessAuthorization;
use App\Shared\Contracts\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function __construct(
        private readonly BusinessContext $business,
        private readonly BusinessAuthorization $authorization,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $accountId = $request->user()?->uuid;

        if ($accountId === null) {
            throw PermissionDenied::forCurrentBusiness();
        }

        $granted = $this->authorization->grants(
            (string) $accountId,
            $this->business->currentBusinessId(),
            $permission,
        );

        if (! $granted) {
            throw PermissionDenied::forCurrentBusiness();
        }

        return $next($request);
    }
}
