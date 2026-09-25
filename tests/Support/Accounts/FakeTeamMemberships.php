<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\TeamMemberships;
use App\Domains\Accounts\Exceptions\AccountHasUpcomingAppointments;

final class FakeTeamMemberships implements TeamMemberships
{
    /**
     * @var array<string, string>
     */
    private array $ownedBusinessIds = [];

    /**
     * @var array<string, true>
     */
    private array $busyElsewhere = [];

    /**
     * @var list<string>
     */
    public array $accountsThatLeft = [];

    /**
     * @var list<string>
     */
    public array $accountsAsked = [];

    public function __construct(
        public readonly AccountJournal $journal = new AccountJournal,
    ) {}

    public function owning(string $accountId, string $businessId): self
    {
        $this->ownedBusinessIds[$accountId] = $businessId;

        return $this;
    }

    public function busyElsewhere(string $accountId): self
    {
        $this->busyElsewhere[$accountId] = true;

        return $this;
    }

    public function ownedBusinessIdOf(string $accountId): ?string
    {
        $this->journal->record('memberships.ownedBusinessIdOf');
        $this->accountsAsked[] = $accountId;

        return $this->ownedBusinessIds[$accountId] ?? null;
    }

    public function hasUpcomingAppointmentsOutsideOwnedBusiness(string $accountId): bool
    {
        $this->journal->record('memberships.hasUpcomingAppointmentsOutsideOwnedBusiness');
        $this->accountsAsked[] = $accountId;

        return isset($this->busyElsewhere[$accountId]);
    }

    public function leaveTeamsNotOwned(string $accountId): void
    {
        $this->journal->record('memberships.leaveTeamsNotOwned');

        if (isset($this->busyElsewhere[$accountId])) {
            throw AccountHasUpcomingAppointments::forAccount($accountId);
        }

        $this->accountsThatLeft[] = $accountId;
    }
}
