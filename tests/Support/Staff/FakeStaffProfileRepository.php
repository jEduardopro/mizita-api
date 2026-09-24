<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\StaffProfileNotFound;
use Throwable;

final class FakeStaffProfileRepository implements StaffProfileRepository
{
    /**
     * @var array<string, StaffProfile>
     */
    private array $profiles = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string}>
     */
    public array $lookups = [];

    /**
     * @var list<StaffProfile>
     */
    public array $saved = [];

    private ?Throwable $saveRefusal = null;

    public function __construct(
        private readonly StaffJournal $journal = new StaffJournal,
    ) {}

    public function store(StaffProfile ...$profiles): self
    {
        foreach ($profiles as $profile) {
            $this->profiles[$profile->id] = self::copyOf($profile);
        }

        return $this;
    }

    public function refuseSaveWith(Throwable $refusal): self
    {
        $this->saveRefusal = $refusal;

        return $this;
    }

    public function stored(string $profileId): ?StaffProfile
    {
        return $this->profiles[$profileId] ?? null;
    }

    public function findForStaffMember(string $businessId, string $staffMemberId): StaffProfile
    {
        $this->lookups[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId];

        foreach ($this->profiles as $profile) {
            if ($profile->businessId === $businessId && $profile->staffMemberId === $staffMemberId) {
                return self::copyOf($profile);
            }
        }

        throw StaffProfileNotFound::forStaffMember($staffMemberId);
    }

    public function save(StaffProfile $profile): void
    {
        $this->journal->record('profiles.save');

        if ($this->saveRefusal !== null) {
            throw $this->saveRefusal;
        }

        $this->saved[] = self::copyOf($profile);
        $this->profiles[$profile->id] = self::copyOf($profile);
    }

    private static function copyOf(StaffProfile $profile): StaffProfile
    {
        return StaffProfile::restore(
            id: $profile->id,
            businessId: $profile->businessId,
            staffMemberId: $profile->staffMemberId,
            jobTitle: $profile->jobTitle(),
            about: $profile->about(),
            createdAt: $profile->createdAt,
        );
    }
}
