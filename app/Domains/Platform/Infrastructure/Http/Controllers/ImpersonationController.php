<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Controllers;

use App\Domains\Platform\Application\Dtos\StartImpersonationInput;
use App\Domains\Platform\Application\UseCases\StartImpersonation;
use App\Domains\Platform\Application\UseCases\StopImpersonation;
use App\Domains\Platform\Infrastructure\Auth\PlatformGuard;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Http\Controllers\Controller;
use App\Http\Responses\WebResponder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class ImpersonationController extends Controller
{
    private const OWNER_LANDING_ROUTE = 'calendar';

    private const ERROR_KEY = 'impersonation';

    public function store(
        Request $request,
        string $business,
        PlatformGuard $guards,
        StartImpersonation $startImpersonation,
        WebResponder $responder,
    ): RedirectResponse {
        try {
            $response = $startImpersonation->handle(new StartImpersonationInput(
                adminId: (string) $guards->signedInAdmin()?->uuid,
                businessId: $business,
            ));

            if ($response->failed()) {
                return $responder->backTo(PlatformRoutes::BUSINESSES, self::ERROR_KEY, $response->error());
            }

            return redirect()->route(self::OWNER_LANDING_ROUTE);
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected, PlatformRoutes::BUSINESSES, self::ERROR_KEY);
        }
    }

    public function destroy(
        Request $request,
        StopImpersonation $stopImpersonation,
        WebResponder $responder,
    ): RedirectResponse {
        try {
            $stopImpersonation->handle();

            return redirect()->route(PlatformRoutes::BUSINESSES);
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected, PlatformRoutes::BUSINESSES, self::ERROR_KEY);
        }
    }
}
