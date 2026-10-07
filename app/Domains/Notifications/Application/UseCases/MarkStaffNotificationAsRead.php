<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\MarkStaffNotificationAsReadInput;
use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Exceptions\NotificationNotAddressedToReader;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class MarkStaffNotificationAsRead
{
    public function __construct(
        private readonly StaffNotificationRepository $notifications,
        private readonly NotificationFeed $feed,
        private readonly NotificationReaders $readers,
        private readonly BusinessContext $business,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<StaffNotificationData>
     */
    public function handle(MarkStaffNotificationAsReadInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $reader = $this->readers->readerFor($businessId, $input->accountId);

            $this->markAsRead($businessId, $input->notificationId, $reader);

            return UseCaseResponse::success(StaffNotificationData::forReader(
                $this->feed->find($businessId, $input->notificationId),
                $reader,
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws StaffNotificationNotFound
     * @throws NotificationNotAddressedToReader
     */
    private function markAsRead(string $businessId, string $notificationId, NotificationReader $reader): void
    {
        $notification = $this->notifications->findForBusiness($businessId, $notificationId);

        if (! $reader->canView($notification->recipientStaffMemberId)) {
            throw StaffNotificationNotFound::withId($notificationId);
        }

        $notification->markAsReadBy($reader->staffMemberId, $this->clock->now());

        $this->notifications->markAsRead($notification);
    }
}
