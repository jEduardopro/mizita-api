<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Eloquent\Mappers;

use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Infrastructure\Eloquent\Models\ScheduleRuleModel;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use DateTimeImmutable;

final class ScheduleRuleMapper
{
    public function toEntity(ScheduleRuleModel $model, string $businessId, string $ownerId): ScheduleRule
    {
        return ScheduleRule::restore(
            id: $model->uuid,
            businessId: $businessId,
            ownerType: ScheduleOwnerType::from($model->owner_type),
            ownerId: $ownerId,
            weekday: Weekday::from($model->weekday),
            startsAt: TimeOfDay::fromString($model->starts_at),
            endsAt: TimeOfDay::fromString($model->ends_at),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(ScheduleRule $rule, int $businessKey, int $ownerKey): array
    {
        return [
            'uuid' => $rule->id,
            'business_id' => $businessKey,
            'owner_type' => $rule->ownerType->value,
            'owner_id' => $ownerKey,
            'weekday' => $rule->weekday->value,
            'starts_at' => $rule->startsAt()->toString(),
            'ends_at' => $rule->endsAt()->toString(),
        ];
    }
}
