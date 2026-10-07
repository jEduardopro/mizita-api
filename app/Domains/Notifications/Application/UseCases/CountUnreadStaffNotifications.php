<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\CountUnreadStaffNotificationsInput;
use App\Domains\Notifications\Application\Dtos\UnreadNotificationCountData;
use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class CountUnreadStaffNotifications
{
    public function __construct(
        private readonly NotificationFeed $feed,
        private readonly NotificationReaders $readers,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<UnreadNotificationCountData>
     */
    public function handle(CountUnreadStaffNotificationsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $reader = $this->readers->readerFor($businessId, $input->accountId);

            return UseCaseResponse::success(new UnreadNotificationCountData(
                $this->feed->countUnread($businessId, $reader->audienceFor($input->scope())),
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
