<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Accounts\Exceptions\PasswordChangeRequired;
use App\Http\Responses\JsonFailureRendering;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireFreshPassword
{
    public const CHANGE_PASSWORD_ROUTE = 'password.change';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustChangePassword()) {
            return $next($request);
        }

        if (JsonFailureRendering::appliesTo($request)) {
            throw PasswordChangeRequired::beforeContinuing();
        }

        return redirect()->route(self::CHANGE_PASSWORD_ROUTE);
    }
}
