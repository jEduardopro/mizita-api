<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarOwners;
use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;

final class FakeCalendarOwners implements CalendarOwners
{
    /**
     * @var array<string, string>
     */
    private array $staffMemberIds = [];

    /**
     * @var list<array{businessId: string, accountId: string}>
     */
    public array $lookups = [];

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
    ) {}

    public function member(string $businessId, string $accountId, string $staffMemberId): self
    {
        $this->staffMemberIds[$businessId.'|'.$accountId] = $staffMemberId;

        return $this;
    }

    public function staffMemberIdOf(string $businessId, string $accountId): string
    {
        $this->journal->record('owners.staffMemberIdOf');
        $this->lookups[] = ['businessId' => $businessId, 'accountId' => $accountId];

        return $this->staffMemberIds[$businessId.'|'.$accountId]
            ?? throw CalendarOwnerNotFound::forAccount($accountId);
    }
}
