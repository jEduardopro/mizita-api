<?php

declare(strict_types=1);

namespace Tests\Support\Availability;

use App\Domains\Availability\Contracts\StaffRoster;
use App\Domains\Availability\Exceptions\StaffMemberNotFound;

final class FakeStaffRoster implements StaffRoster
{
    /**
     * @var array<string, true>
     */
    private array $members = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string}>
     */
    public array $confirmations = [];

    public function enrol(string $businessId, string $staffMemberId): self
    {
        $this->members[$this->keyFor($businessId, $staffMemberId)] = true;

        return $this;
    }

    public function confirmMembership(string $businessId, string $staffMemberId): void
    {
        $this->confirmations[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId];

        if (! isset($this->members[$this->keyFor($businessId, $staffMemberId)])) {
            throw StaffMemberNotFound::inBusiness($staffMemberId, $businessId);
        }
    }

    private function keyFor(string $businessId, string $staffMemberId): string
    {
        return $businessId.'|'.$staffMemberId;
    }
}
