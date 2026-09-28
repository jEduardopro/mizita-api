<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\TeamAllowance;

final class FakeTeamAllowance implements TeamAllowance
{
    /**
     * @var array<string, true>
     */
    private array $withoutTeam = [];

    /**
     * @var list<string>
     */
    public array $checks = [];

    /**
     * @var list<list<string>>
     */
    public array $batchChecks = [];

    public function __construct(
        private readonly StaffJournal $journal = new StaffJournal,
    ) {}

    public function withoutTeamFor(string ...$businessIds): self
    {
        foreach ($businessIds as $businessId) {
            $this->withoutTeam[$businessId] = true;
        }

        return $this;
    }

    public function includesTeam(string $businessId): bool
    {
        $this->journal->record('allowance.team');
        $this->checks[] = $businessId;

        return ! isset($this->withoutTeam[$businessId]);
    }

    /**
     * @param  list<string>  $businessIds
     * @return list<string>
     */
    public function businessesIncludingTeam(array $businessIds): array
    {
        $this->batchChecks[] = $businessIds;

        return array_values(array_filter(
            $businessIds,
            fn (string $businessId): bool => ! isset($this->withoutTeam[$businessId]),
        ));
    }
}
