<?php

declare(strict_types=1);

namespace App\Http\Logging;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Throwable;

final readonly class FailureLogContext
{
    /**
     * @return array{exception: Throwable, route: string|null, method: string, path: string, user_id: string|null}
     */
    public static function for(Request $request, Throwable $error): array
    {
        return [
            'exception' => $error,
            'route' => self::routeNameOf($request),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => self::userIdOf($request),
        ];
    }

    /**
     * @return array{exception: Throwable}
     */
    public static function withoutRequest(Throwable $error): array
    {
        return ['exception' => $error];
    }

    private static function routeNameOf(Request $request): ?string
    {
        $route = $request->route();

        return $route instanceof Route ? $route->getName() : null;
    }

    private static function userIdOf(Request $request): ?string
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return is_string($user->uuid) ? $user->uuid : null;
    }
}
