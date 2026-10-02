<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Controllers;

use App\Domains\Platform\Infrastructure\Auth\PlatformSignOut;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PlatformLogoutController extends Controller
{
    public function __invoke(Request $request, PlatformSignOut $signOut): RedirectResponse
    {
        $signOut->signOut();

        $request->session()->regenerateToken();

        return redirect()->route(PlatformRoutes::LOGIN);
    }
}
