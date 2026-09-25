<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;

interface CalendarOwners
{
    /**
     * @throws CalendarOwnerNotFound
     */
    public function staffMemberIdOf(string $businessId, string $accountId): string;
}
