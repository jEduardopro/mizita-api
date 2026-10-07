<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\ListStaffNotificationsInput;
use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Paginated;

final class ListStaffNotifications
{
    public function __construct(
        private readonly NotificationFeed $feed,
        private readonly NotificationReaders $readers,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<Paginated<StaffNotificationData>>
     */
    public function handle(ListStaffNotificationsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $reader = $this->readers->readerFor($businessId, $input->accountId);

            $page = $this->feed->newestFirst(
                $businessId,
                $reader->audienceFor($input->scope()),
                $input->status(),
                $input->pagination(),
            );

            return UseCaseResponse::success($page->map(
                static fn (NotificationRecord $record): StaffNotificationData => StaffNotificationData::forReader($record, $reader),
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
