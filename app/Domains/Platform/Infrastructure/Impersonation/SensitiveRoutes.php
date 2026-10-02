<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Impersonation;

use Illuminate\Http\Request;

final class SensitiveRoutes
{
    private const ANY_METHOD = '*';

    /**
     * @var array<string, string>
     */
    private const OFF_LIMITS_WHILE_IMPERSONATING = [
        'user/*' => self::ANY_METHOD,
        'passkeys/confirm*' => self::ANY_METHOD,
        'api/me/account*' => self::ANY_METHOD,
        'api/subscription/*' => Request::METHOD_POST,
        'api/integrations/google-calendar/*' => self::ANY_METHOD,
        'integrations/google-calendar/callback' => self::ANY_METHOD,
        'api/staff-members/*/temporary-password' => self::ANY_METHOD,
    ];

    public function matches(Request $request): bool
    {
        foreach (self::OFF_LIMITS_WHILE_IMPERSONATING as $pathPattern => $method) {
            if ($request->is($pathPattern) && $this->methodMatches($request, $method)) {
                return true;
            }
        }

        return false;
    }

    private function methodMatches(Request $request, string $method): bool
    {
        return $method === self::ANY_METHOD || $request->isMethod($method);
    }
}
