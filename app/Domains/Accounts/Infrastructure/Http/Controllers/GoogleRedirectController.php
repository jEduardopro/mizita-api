<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

final class GoogleRedirectController extends Controller
{
    private const DRIVER = 'google';

    public function __invoke(): RedirectResponse
    {
        return Socialite::driver(self::DRIVER)->redirect();
    }
}
