<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

/**
 * Socialite puts the "state" value in the session here, which is why this route
 * is on the web stack.
 */
final class GoogleRedirectController extends Controller
{
    private const DRIVER = 'google';

    public function __invoke(): RedirectResponse
    {
        return Socialite::driver(self::DRIVER)->redirect();
    }
}
