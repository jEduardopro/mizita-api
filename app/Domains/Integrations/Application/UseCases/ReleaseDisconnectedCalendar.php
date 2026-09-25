<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\ReleaseDisconnectedCalendarInput;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarProvisioning;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Shared\Application\UseCaseResponse;

final class ReleaseDisconnectedCalendar
{
    public function __construct(
        private readonly CalendarConnectionRepository $connections,
        private readonly CalendarProvisioning $provisioning,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(ReleaseDisconnectedCalendarInput $input): UseCaseResponse
    {
        $connection = $this->connections->findDisconnected($input->businessId, $input->connectionId);

        if ($connection === null) {
            return UseCaseResponse::success();
        }

        $this->provisioning->deleteCalendar($connection);

        if (! $this->isAuthorizationStillInUse($connection)) {
            $this->provisioning->revokeAuthorization($connection);
        }

        return UseCaseResponse::success();
    }

    private function isAuthorizationStillInUse(CalendarConnection $connection): bool
    {
        return $this->connections->existsLiveForAccount($connection->provider, $connection->accountEmail());
    }
}
