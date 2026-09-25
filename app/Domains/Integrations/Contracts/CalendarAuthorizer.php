<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarScopeNotGranted;
use App\Domains\Integrations\ValueObjects\CalendarGrant;

interface CalendarAuthorizer
{
    public function authorizationUrl(string $state): string;

    /**
     * @throws CalendarAuthorizationFailed
     * @throws CalendarScopeNotGranted
     */
    public function exchange(string $code): CalendarGrant;
}
