<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\TeamOwnership;

final class FakeTeamOwnership implements TeamOwnership
{
    /**
     * @var array<string, string>
     */
    private array $owners = [];

    /**
     * @var list<string>
     */
    public array $lookups = [];

    public function ownedBy(string $businessId, string $staffMemberId): self
    {
        $this->owners[$businessId] = $staffMemberId;

        return $this;
    }

    public function ownerStaffMemberIdOf(string $businessId): ?string
    {
        $this->lookups[] = $businessId;

        return $this->owners[$businessId] ?? null;
    }
}
