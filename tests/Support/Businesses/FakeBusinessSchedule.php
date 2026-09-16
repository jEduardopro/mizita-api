<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use Throwable;

final class FakeBusinessSchedule implements BusinessSchedule
{
    /**
     * @var array<string, list<BusinessScheduleEntry>>
     */
    private array $entries = [];

    private ?Throwable $replaceFailure = null;

    /**
     * @var list<array{businessId: string, entries: list<BusinessScheduleEntry>}>
     */
    public array $replacements = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function store(string $businessId, BusinessScheduleEntry ...$entries): self
    {
        $this->entries[$businessId] = array_values($entries);

        return $this;
    }

    public function failingOnReplace(Throwable $failure): self
    {
        $this->replaceFailure = $failure;

        return $this;
    }

    /**
     * @return list<BusinessScheduleEntry>
     */
    public function forBusiness(string $businessId): array
    {
        $this->reads[] = $businessId;

        return $this->entries[$businessId] ?? [];
    }

    /**
     * @param  list<BusinessScheduleEntry>  $entries
     */
    public function replaceForBusiness(string $businessId, array $entries): void
    {
        if ($this->replaceFailure !== null) {
            throw $this->replaceFailure;
        }

        $this->entries[$businessId] = $entries;
        $this->replacements[] = ['businessId' => $businessId, 'entries' => $entries];
    }
}
