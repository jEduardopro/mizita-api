<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;

interface ScheduleRuleRepository
{
    /**
     * @return list<ScheduleRule>
     */
    public function allForOwner(ScheduleOwnerType $ownerType, string $ownerId): array;

    /**
     * @param  list<ScheduleRule>  $rules
     */
    public function replaceForOwner(
        string $businessId,
        ScheduleOwnerType $ownerType,
        string $ownerId,
        array $rules,
    ): void;

    public function deleteForOwner(ScheduleOwnerType $ownerType, string $ownerId): void;
}
