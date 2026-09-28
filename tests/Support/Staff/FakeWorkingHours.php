<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\WorkingHours;

final class FakeWorkingHours implements WorkingHours
{
    /**
     * @var array<string, true>
     */
    private array $working = [];

    /**
     * @var list<array{businessId: string, staffMemberIds: list<string>}>
     */
    public array $lookups = [];

    public function working(string $businessId, string ...$staffMemberIds): self
    {
        foreach ($staffMemberIds as $staffMemberId) {
            $this->working[$businessId.'|'.$staffMemberId] = true;
        }

        return $this;
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @return list<string>
     */
    public function staffWithWorkingHours(string $businessId, array $staffMemberIds): array
    {
        $this->lookups[] = ['businessId' => $businessId, 'staffMemberIds' => array_values($staffMemberIds)];

        return array_values(array_filter(
            $staffMemberIds,
            fn (string $staffMemberId): bool => isset($this->working[$businessId.'|'.$staffMemberId]),
        ));
    }
}
