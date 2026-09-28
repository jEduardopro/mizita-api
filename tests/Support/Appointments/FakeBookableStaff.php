<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\BookableStaff;
use App\Domains\Appointments\Exceptions\AppointmentStaffMemberPaused;

final class FakeBookableStaff implements BookableStaff
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $pausedByBusiness = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string}>
     */
    public array $confirmations = [];

    public function __construct(
        public readonly AppointmentJournal $journal = new AppointmentJournal,
    ) {}

    public function pause(string $businessId, string ...$staffMemberIds): self
    {
        foreach ($staffMemberIds as $staffMemberId) {
            $this->pausedByBusiness[$businessId][$staffMemberId] = true;
        }

        return $this;
    }

    public function confirmBookable(string $businessId, string $staffMemberId): void
    {
        $this->journal->record('staff.confirmBookable');
        $this->confirmations[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId];

        if (isset($this->pausedByBusiness[$businessId][$staffMemberId])) {
            throw AppointmentStaffMemberPaused::withId($staffMemberId);
        }
    }
}
