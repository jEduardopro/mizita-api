<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAlreadyConnected;
use App\Domains\Integrations\Exceptions\CalendarConnectionNotFound;
use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\CalendarTokens;

interface CalendarConnectionRepository
{
    public function findForStaffMember(string $businessId, string $staffMemberId, CalendarProvider $provider): ?CalendarConnection;

    public function findInBusiness(string $businessId, string $id): ?CalendarConnection;

    public function findDisconnected(string $businessId, string $id): ?CalendarConnection;

    public function existsLiveForAccount(CalendarProvider $provider, string $accountEmail): bool;

    /**
     * @throws CalendarAlreadyConnected
     * @throws CalendarOwnerNotFound
     */
    public function saveWithTokens(CalendarConnection $connection, CalendarTokens $tokens): void;

    /**
     * @throws CalendarConnectionNotFound
     */
    public function update(CalendarConnection $connection): void;

    /**
     * @throws CalendarConnectionNotFound
     */
    public function delete(string $businessId, string $id): void;
}
