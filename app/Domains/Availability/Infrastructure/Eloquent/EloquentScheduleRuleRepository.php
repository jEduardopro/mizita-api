<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Eloquent;

use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Infrastructure\Eloquent\Mappers\ScheduleRuleMapper;
use App\Domains\Availability\Infrastructure\Eloquent\Models\ScheduleRuleModel;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;

final class EloquentScheduleRuleRepository implements ScheduleRuleRepository
{
    private const BUSINESS_SELECTION = 'business:id,uuid';

    public function __construct(
        private readonly ScheduleRuleMapper $mapper,
        private readonly BusinessTeamKey $teamKeys,
    ) {}

    /**
     * @return list<ScheduleRule>
     */
    public function allForOwner(ScheduleOwnerType $ownerType, string $ownerId): array
    {
        return $this->ownedBy($ownerType, $ownerId)
            ->with(self::BUSINESS_SELECTION)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ScheduleRuleModel $model): ScheduleRule => $this->mapper->toEntity(
                $model,
                (string) $model->business?->uuid,
                $ownerId,
            ))
            ->values()
            ->all();
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
        $businessKey = $this->teamKeys->teamKeyFor($businessId);
        $ownerKey = $this->ownerKey($ownerType, $ownerId);

        ScheduleRuleModel::query()
            ->where('business_id', $businessKey)
            ->where('owner_type', $ownerType->value)
            ->where('owner_id', $ownerKey)
            ->delete();

        foreach ($rules as $rule) {
            ScheduleRuleModel::query()->create(
                $this->mapper->toAttributes($rule, $businessKey, $ownerKey),
            );
        }
    }

    public function deleteForOwner(ScheduleOwnerType $ownerType, string $ownerId): void
    {
        $this->ownedBy($ownerType, $ownerId)->delete();
    }

    /**
     * @return Builder<ScheduleRuleModel>
     */
    private function ownedBy(ScheduleOwnerType $ownerType, string $ownerId): Builder
    {
        return ScheduleRuleModel::query()
            ->where('owner_type', $ownerType->value)
            ->where('owner_id', $this->ownerKey($ownerType, $ownerId));
    }

    private function ownerKey(ScheduleOwnerType $ownerType, string $ownerId): int
    {
        /** @var class-string<Model>|null $owner */
        $owner = Relation::getMorphedModel($ownerType->value);

        if ($owner === null) {
            throw (new ModelNotFoundException)->setModel($ownerType->value, [$ownerId]);
        }

        $key = $owner::query()->where('uuid', $ownerId)->value('id');

        if ($key === null) {
            throw (new ModelNotFoundException)->setModel($owner, [$ownerId]);
        }

        return (int) $key;
    }
}
