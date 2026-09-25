<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;
use App\Domains\Integrations\ValueObjects\CalendarGrant;

interface CalendarProvisioning
{
    /**
     * @throws CalendarAuthorizationFailed
     */
    public function createCalendar(CalendarGrant $grant, BusinessCalendarProfile $business): string;

    /**
     * @throws CalendarAuthorizationFailed
     */
    public function adoptCalendar(CalendarGrant $grant, string $externalCalendarId, BusinessCalendarProfile $business): string;

    public function deleteCalendar(CalendarConnection $connection): void;

    public function discardCalendar(CalendarGrant $grant, string $externalCalendarId): void;

    public function revokeAuthorization(CalendarConnection $connection): void;

    public function revokeGrant(CalendarGrant $grant): void;
}
