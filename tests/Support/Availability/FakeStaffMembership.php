<?php

declare(strict_types=1);

namespace Tests\Support\Availability;

use App\Domains\Availability\Contracts\StaffMembership;
use App\Domains\Availability\Exceptions\StaffMembershipNotFound;

final class FakeStaffMembership implements StaffMembership
{
    /**
     * @var array<string, string>
     */
    private array $staffIds = [];

    /**
     * @var list<array{businessId: string, accountId: string}>
     */
    public array $lookups = [];

    public function grant(string $businessId, string $accountId, string $staffMemberId): self
    {
        $this->staffIds[$this->keyFor($businessId, $accountId)] = $staffMemberId;

        return $this;
    }

    public function staffMemberIdOf(string $businessId, string $accountId): string
    {
        $this->lookups[] = ['businessId' => $businessId, 'accountId' => $accountId];

        return $this->staffIds[$this->keyFor($businessId, $accountId)]
            ?? throw StaffMembershipNotFound::forAccount($accountId, $businessId);
    }

    private function keyFor(string $businessId, string $accountId): string
    {
        return $businessId.'|'.$accountId;
    }
}
