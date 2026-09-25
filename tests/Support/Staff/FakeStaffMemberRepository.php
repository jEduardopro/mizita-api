<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use Throwable;

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
     * @var list<array{businessId: string, id: string}>
     */
    public array $businessLookups = [];

    /**
     * @var list<StaffMember>
     */
    public array $saved = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $deleted = [];

    /**
     * @var list<array{scope: string, accountId: string}>
     */
    public array $membershipLookups = [];

    /**
     * @var array<string, true>
     */
    private array $closedBusinesses = [];

    private ?Throwable $saveRefusal = null;

    private int $savesBeforeRefusal = 0;

    private ?Throwable $deleteRefusal = null;

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

    public function refuseSaveWith(Throwable $refusal, int $afterSaves = 0): self
    {
        $this->saveRefusal = $refusal;
        $this->savesBeforeRefusal = $afterSaves;

        return $this;
    }

    public function closeBusiness(string $businessId): self
    {
        $this->closedBusinesses[$businessId] = true;

        return $this;
    }

    public function refuseDeleteWith(Throwable $refusal): self
    {
        $this->deleteRefusal = $refusal;

        return $this;
    }

    public function stored(string $id): ?StaffMember
    {
        return isset($this->members[$id]) ? self::copyOf($this->members[$id]) : null;
    }

    public function save(StaffMember $member): void
    {
        $this->journal->record('members.save');

        if ($this->saveRefusal !== null && count($this->saved) >= $this->savesBeforeRefusal) {
            throw $this->saveRefusal;
        }

        $this->saved[] = $member;
        $this->members[$member->id] = $member;
    }

    public function findById(string $id): StaffMember
    {
        return $this->members[$id] ?? throw StaffMemberNotFound::withId($id);
    }

    public function findForBusiness(string $businessId, string $id): StaffMember
    {
        $this->businessLookups[] = ['businessId' => $businessId, 'id' => $id];

        $member = $this->members[$id] ?? null;

        if ($member === null || $member->businessId !== $businessId) {
            throw StaffMemberNotFound::withId($id);
        }

        return self::copyOf($member);
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

    /**
     * @return list<StaffMember>
     */
    public function allForAccount(string $accountId): array
    {
        $this->membershipLookups[] = ['scope' => 'all', 'accountId' => $accountId];

        return $this->membershipsOf($accountId);
    }

    /**
     * @return list<StaffMember>
     */
    public function allInOpenBusinessesForAccount(string $accountId): array
    {
        $this->membershipLookups[] = ['scope' => 'open', 'accountId' => $accountId];

        return array_values(array_filter(
            $this->membershipsOf($accountId),
            fn (StaffMember $member): bool => ! isset($this->closedBusinesses[$member->businessId]),
        ));
    }

    public function ownedBusinessIdOf(string $accountId): ?string
    {
        foreach ($this->membershipsOf($accountId) as $member) {
            if ($member->ownsBusiness()) {
                return $member->businessId;
            }
        }

        return null;
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

    public function delete(string $businessId, string $id): void
    {
        $this->journal->record('members.delete');

        if ($this->deleteRefusal !== null) {
            throw $this->deleteRefusal;
        }

        $member = $this->members[$id] ?? null;

        if ($member === null || $member->businessId !== $businessId) {
            throw StaffMemberNotFound::withId($id);
        }

        $this->deleted[] = ['businessId' => $businessId, 'id' => $id];
        unset($this->members[$id]);
    }

    /**
     * @return list<StaffMember>
     */
    private function membershipsOf(string $accountId): array
    {
        return array_values(array_map(
            self::copyOf(...),
            array_filter(
                $this->members,
                static fn (StaffMember $member): bool => $member->accountId === $accountId,
            ),
        ));
    }

    private static function copyOf(StaffMember $member): StaffMember
    {
        return StaffMember::restore(
            id: $member->id,
            businessId: $member->businessId,
            accountId: $member->accountId,
            role: $member->role(),
            createdAt: $member->createdAt,
        );
    }
}
