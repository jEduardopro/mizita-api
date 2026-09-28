<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\ServiceAssignments;

final class FakeServiceAssignments implements ServiceAssignments
{
    /**
     * @var array<string, true>
     */
    private array $offering = [];

    /**
     * @var list<array{businessId: string, staffMemberIds: list<string>}>
     */
    public array $lookups = [];

    public function offering(string $businessId, string ...$staffMemberIds): self
    {
        foreach ($staffMemberIds as $staffMemberId) {
            $this->offering[$businessId.'|'.$staffMemberId] = true;
        }

        return $this;
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @return list<string>
     */
    public function staffOfferingServices(string $businessId, array $staffMemberIds): array
    {
        $this->lookups[] = ['businessId' => $businessId, 'staffMemberIds' => array_values($staffMemberIds)];

        return array_values(array_filter(
            $staffMemberIds,
            fn (string $staffMemberId): bool => isset($this->offering[$businessId.'|'.$staffMemberId]),
        ));
    }
}
