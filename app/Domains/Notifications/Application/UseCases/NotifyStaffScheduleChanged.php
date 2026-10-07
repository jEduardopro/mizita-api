<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\NotifyStaffScheduleChangedInput;
use App\Domains\Notifications\Contracts\BusinessOwners;
use App\Domains\Notifications\Contracts\NotifiedStaffMembers;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\NotificationEvent;
use App\Domains\Notifications\Exceptions\NotifiedStaffMemberNotFound;
use App\Domains\Notifications\ValueObjects\Payloads\StaffScheduleChangedPayload;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class NotifyStaffScheduleChanged
{
    public function __construct(
        private readonly BusinessOwners $owners,
        private readonly NotifiedStaffMembers $staffMembers,
        private readonly StaffNotificationRepository $notifications,
        private readonly TransactionManager $transactions,
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

    /**
     * @throws NotifiedStaffMemberNotFound
     */
    private function notifyOwnerOf(string $businessId, string $staffMemberId): void
    {
        $ownerStaffMemberId = $this->owners->ownerStaffMemberIdOf($businessId);

        if ($ownerStaffMemberId === null || $ownerStaffMemberId === $staffMemberId) {
            return;
        }

        $event = NotificationEvent::record(
            id: $this->ids->next(),
            businessId: $businessId,
            payload: new StaffScheduleChangedPayload($this->staffMembers->describe($businessId, $staffMemberId)),
            occurredAt: $this->clock->now(),
        );

        $delivery = $event->deliverTo($this->ids->next(), $ownerStaffMemberId);

        $this->transactions->run(fn () => $this->notifications->record($event, $delivery));
    }
}
