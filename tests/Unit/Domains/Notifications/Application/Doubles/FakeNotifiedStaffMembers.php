<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Contracts\NotifiedStaffMembers;
use App\Domains\Notifications\Exceptions\NotifiedStaffMemberNotFound;
use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;

final class FakeNotifiedStaffMembers implements NotifiedStaffMembers
{
    /**
     * @var array<string, NotifiedStaffMember>
     */
    private array $staffMembers = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string}>
     */
    public array $lookups = [];

    public function __construct(
        private readonly NotificationsJournal $journal = new NotificationsJournal,
    ) {}

    public function add(string $businessId, NotifiedStaffMember $staffMember): self
    {
        $this->staffMembers[$businessId.'|'.$staffMember->staffMemberId] = $staffMember;

        return $this;
    }

    public function describe(string $businessId, string $staffMemberId): NotifiedStaffMember
    {
        $this->journal->record('staffMembers.describe');
        $this->lookups[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId];

        return $this->staffMembers[$businessId.'|'.$staffMemberId]
            ?? throw NotifiedStaffMemberNotFound::inBusiness($businessId, $staffMemberId);
    }
}
