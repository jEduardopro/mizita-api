<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\NotifyStaffScheduleChangedInput;
use App\Domains\Notifications\Contracts\BusinessOwners;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\StaffNotification;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class NotifyStaffScheduleChanged
{
    public function __construct(
        private readonly BusinessOwners $owners,
        private readonly StaffNotificationRepository $notifications,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(NotifyStaffScheduleChangedInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $this->notifyOwnerOf($input->businessId, $input->staffMemberId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }

    private function notifyOwnerOf(string $businessId, string $staffMemberId): void
    {
        $ownerStaffMemberId = $this->owners->ownerStaffMemberIdOf($businessId);

        if ($ownerStaffMemberId === null || $ownerStaffMemberId === $staffMemberId) {
            return;
        }

        $this->notifications->addOrRefreshUnread(StaffNotification::staffScheduleChanged(
            id: $this->ids->next(),
            businessId: $businessId,
            recipientStaffMemberId: $ownerStaffMemberId,
            subjectStaffMemberId: $staffMemberId,
            now: $this->clock->now(),
        ));
    }
}
