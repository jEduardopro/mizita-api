<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Contracts\StaffDirectory;
use App\Domains\Services\ValueObjects\StaffMemberSnapshot;

final class FakeStaffDirectory implements StaffDirectory
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $membersByBusiness = [];

    /**
     * @var list<array{businessId: string, staffIds: list<string>}>
     */
    public array $calls = [];

    /**
     * @param  array<string, string>  $members
     */
    public static function of(string $businessId, array $members): self
    {
        return (new self)->add($businessId, $members);
    }

    /**
     * @param  array<string, string>  $members
     */
    public function add(string $businessId, array $members): self
    {
        $this->membersByBusiness[$businessId] = [
            ...($this->membersByBusiness[$businessId] ?? []),
            ...$members,
        ];

        return $this;
    }

    /**
     * @param  list<string>  $staffIds
     * @return list<StaffMemberSnapshot>
     */
    public function membersOf(string $businessId, array $staffIds): array
    {
        $this->calls[] = ['businessId' => $businessId, 'staffIds' => array_values($staffIds)];

        $known = $this->membersByBusiness[$businessId] ?? [];
        $snapshots = [];

        foreach ($staffIds as $staffId) {
            if (isset($known[$staffId])) {
                $snapshots[] = new StaffMemberSnapshot($staffId, $known[$staffId]);
            }
        }

        return $snapshots;
    }

    public function callCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return array{businessId: string, staffIds: list<string>}
     */
    public function lastCall(): array
    {
        return $this->calls[count($this->calls) - 1];
    }
}
