<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Preferences\CookiePreferences;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class HandlePreferences
{
    public function __construct(private readonly CookiePreferences $preferences) {}

    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $this->preferences->appearance($request)->value);

        return $next($request);
    }
}
