<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;

interface CalendarAuthorizationStates
{
    public function issue(PendingAuthorization $pending): string;

    /**
     * @throws CalendarAuthorizationStateInvalid
     */
    public function consume(string $state): PendingAuthorization;
}
