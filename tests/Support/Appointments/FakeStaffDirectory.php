<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\StaffDirectory;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;

final class FakeStaffDirectory implements StaffDirectory
{
    /**
     * @var array<string, array<string, StaffMemberSnapshot>>
     */
    private array $membersByBusiness = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{businessId: string, staffMemberIds: list<string>}>
     */
    public array $batchReads = [];

    public function __construct(
        public readonly AppointmentJournal $journal = new AppointmentJournal,
    ) {}

    public static function of(string $businessId, StaffMemberSnapshot ...$members): self
    {
        return (new self)->add($businessId, ...$members);
    }

    public function add(string $businessId, StaffMemberSnapshot ...$members): self
    {
        foreach ($members as $member) {
            $this->membersByBusiness[$businessId][$member->id] = $member;
        }

        return $this;
    }

    public function describe(string $businessId, string $staffMemberId): StaffMemberSnapshot
    {
        $this->journal->record('staff.describe');
        $this->reads[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId];

        return $this->membersByBusiness[$businessId][$staffMemberId]
            ?? throw AppointmentStaffNotFound::withId($staffMemberId);
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @return array<string, StaffMemberSnapshot>
     */
    public function describeMany(string $businessId, array $staffMemberIds): array
    {
        $this->journal->record('staff.describeMany');
        $this->batchReads[] = ['businessId' => $businessId, 'staffMemberIds' => array_values($staffMemberIds)];

        $known = $this->membersByBusiness[$businessId] ?? [];
        $found = [];

        foreach ($staffMemberIds as $staffMemberId) {
            if (isset($known[$staffMemberId])) {
                $found[$staffMemberId] = $known[$staffMemberId];
            }
        }

        return $found;
    }
}
