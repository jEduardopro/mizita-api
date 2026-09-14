<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\Request;

final readonly class JsonFailureRendering
{
    public static function appliesTo(Request $request): bool
    {
        return ! $request->hasHeader('X-Inertia')
            && ($request->is('api/*') || $request->expectsJson());
    }
}
