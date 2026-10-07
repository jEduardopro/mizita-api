<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Contracts\BusinessOwners;

final class FakeBusinessOwners implements BusinessOwners
{
    /**
     * @var array<string, string>
     */
    private array $owners = [];

    /**
     * @var list<string>
     */
    public array $lookups = [];

    public function __construct(
        private readonly NotificationsJournal $journal = new NotificationsJournal,
    ) {}

    public function ownedBy(string $businessId, string $staffMemberId): self
    {
        $this->owners[$businessId] = $staffMemberId;

        return $this;
    }

    public function ownerStaffMemberIdOf(string $businessId): ?string
    {
        $this->journal->record('owners.ownerStaffMemberIdOf');
        $this->lookups[] = $businessId;

        return $this->owners[$businessId] ?? null;
    }
}
