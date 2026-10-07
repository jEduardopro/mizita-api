<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\ShowStaffNotificationInput;
use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ShowStaffNotification
{
    public function __construct(
        private readonly NotificationFeed $feed,
        private readonly NotificationReaders $readers,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<StaffNotificationData>
     */
    public function handle(ShowStaffNotificationInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $reader = $this->readers->readerFor($businessId, $input->accountId);
            $record = $this->visibleRecord($businessId, $input->notificationId, $reader);

            return UseCaseResponse::success(StaffNotificationData::forReader($record, $reader));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws StaffNotificationNotFound
     */
    private function visibleRecord(string $businessId, string $notificationId, NotificationReader $reader): NotificationRecord
    {
        $record = $this->feed->find($businessId, $notificationId);

        if (! $record->isVisibleTo($reader)) {
            throw StaffNotificationNotFound::withId($notificationId);
        }

        return $record;
    }
}
