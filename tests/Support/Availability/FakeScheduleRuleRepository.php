<?php

declare(strict_types=1);

namespace Tests\Support\Availability;

use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;

final class FakeScheduleRuleRepository implements ScheduleRuleRepository
{
    /**
     * @var array<string, list<ScheduleRule>>
     */
    private array $rules = [];

    /**
     * @var list<array{businessId: string, ownerType: ScheduleOwnerType, ownerId: string, rules: list<ScheduleRule>}>
     */
    public array $replacements = [];

    /**
     * @var list<array{ownerType: ScheduleOwnerType, ownerId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{ownerType: ScheduleOwnerType, ownerId: string}>
     */
    public array $deletions = [];

    public function store(ScheduleOwnerType $ownerType, string $ownerId, ScheduleRule ...$rules): self
    {
        $this->rules[$this->keyFor($ownerType, $ownerId)] = array_values($rules);

        return $this;
    }

    /**
     * @return list<ScheduleRule>
     */
    public function allForOwner(ScheduleOwnerType $ownerType, string $ownerId): array
    {
        $this->reads[] = ['ownerType' => $ownerType, 'ownerId' => $ownerId];

        return $this->rules[$this->keyFor($ownerType, $ownerId)] ?? [];
    }

    /**
     * @param  list<ScheduleRule>  $rules
     */
    public function replaceForOwner(
        string $businessId,
        ScheduleOwnerType $ownerType,
        string $ownerId,
        array $rules,
    ): void {
        $this->replacements[] = [
            'businessId' => $businessId,
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
            'rules' => array_values($rules),
        ];

        $this->rules[$this->keyFor($ownerType, $ownerId)] = array_values($rules);
    }

    public function deleteForOwner(ScheduleOwnerType $ownerType, string $ownerId): void
    {
        $this->deletions[] = ['ownerType' => $ownerType, 'ownerId' => $ownerId];

        unset($this->rules[$this->keyFor($ownerType, $ownerId)]);
    }

    /**
     * @return list<ScheduleRule>
     */
    public function lastReplacement(): array
    {
        $last = $this->replacements[count($this->replacements) - 1] ?? null;

        return $last['rules'] ?? [];
    }

    private function keyFor(ScheduleOwnerType $ownerType, string $ownerId): string
    {
        return $ownerType->value.'|'.$ownerId;
    }
}
