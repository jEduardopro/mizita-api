<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Impersonation;

use Illuminate\Http\Request;

final class SensitiveRoutes
{
    private const ANY_METHOD = '*';

    private const EVERY_METHOD = [self::ANY_METHOD];

    private const WRITE_METHODS = [
        Request::METHOD_POST,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
        Request::METHOD_DELETE,
    ];

    private const MEMBER_CHANGE_METHODS = [
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
        Request::METHOD_DELETE,
    ];

    /**
     * @var array<string, list<string>>
     */
    private const OFF_LIMITS_WHILE_IMPERSONATING = [
        '#^user/#u' => self::EVERY_METHOD,
        '#^passkeys/confirm#u' => self::EVERY_METHOD,
        '#^api/me/account#u' => self::EVERY_METHOD,
        '#^api/subscription/#u' => [Request::METHOD_POST],
        '#^api/integrations/google-calendar/#u' => self::EVERY_METHOD,
        '#^integrations/google-calendar/callback\z#u' => self::EVERY_METHOD,
        '#^api/staff-members\z#u' => [Request::METHOD_POST],
        '#^api/staff-members/[^/]+\z#u' => self::MEMBER_CHANGE_METHODS,
        '#^api/staff-members/[^/]+/invitation\z#u' => [Request::METHOD_POST],
        '#^api/staff-members/[^/]+/temporary-password\z#u' => self::EVERY_METHOD,
        '#^api/business/settings\z#u' => self::WRITE_METHODS,
    ];

    public function matches(Request $request): bool
    {
        $path = $request->decodedPath();

        foreach (self::OFF_LIMITS_WHILE_IMPERSONATING as $pathPattern => $methods) {
            if (preg_match($pathPattern, $path) === 1 && $this->methodMatches($request, $methods)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $methods
     */
    private function methodMatches(Request $request, array $methods): bool
    {
        return in_array(self::ANY_METHOD, $methods, strict: true)
            || in_array($request->getMethod(), $methods, strict: true);
    }
}
