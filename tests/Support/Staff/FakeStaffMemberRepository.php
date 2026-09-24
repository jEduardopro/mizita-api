<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;

final class FakeStaffMemberRepository implements StaffMemberRepository
{
    /**
     * @var array<string, StaffMember>
     */
    private array $members = [];

    /**
     * @var list<array{businessId: string, accountId: string}>
     */
    public array $accountLookups = [];

    /**
     * @var list<StaffMember>
     */
    public array $saved = [];

    public function __construct(
        private readonly StaffJournal $journal = new StaffJournal,
    ) {}

    public function store(StaffMember ...$members): self
    {
        foreach ($members as $member) {
            $this->members[$member->id] = $member;
        }

        return $this;
    }

    public function save(StaffMember $member): void
    {
        $this->journal->record('members.save');
        $this->saved[] = $member;
        $this->members[$member->id] = $member;
    }

    public function findById(string $id): StaffMember
    {
        return $this->members[$id] ?? throw StaffMemberNotFound::withId($id);
    }

    public function findForAccount(string $businessId, string $accountId): StaffMember
    {
        $this->accountLookups[] = ['businessId' => $businessId, 'accountId' => $accountId];

        foreach ($this->members as $member) {
            if ($member->businessId === $businessId && $member->accountId === $accountId) {
                return $member;
            }
        }

        throw StaffMemberNotFound::forAccount($accountId);
    }

    /**
     * @return list<StaffMember>
     */
    public function allForBusiness(string $businessId): array
    {
        return array_values(array_filter(
            $this->members,
            static fn (StaffMember $member): bool => $member->businessId === $businessId,
        ));
    }

    /**
     * @param  list<string>  $ids
     * @return list<StaffMember>
     */
    public function findManyIncludingArchived(string $businessId, array $ids): array
    {
        return array_values(array_filter(
            $this->allForBusiness($businessId),
            static fn (StaffMember $member): bool => in_array($member->id, $ids, true),
        ));
    }

    public function ownsAnyBusiness(string $accountId): bool
    {
        foreach ($this->members as $member) {
            if ($member->accountId === $accountId && $member->role() === StaffRole::Owner) {
                return true;
            }
        }

        return false;
    }
}
