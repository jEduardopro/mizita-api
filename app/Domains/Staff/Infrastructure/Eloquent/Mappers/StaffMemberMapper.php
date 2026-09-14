<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent\Mappers;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;

/**
 * Three of the entity's values are not on the model's row: business_id and
 * account_id are int primary keys in the database and uuids on the entity, and
 * the role lives in Spatie's tables. All three are passed in resolved.
 */
final class StaffMemberMapper
{
    public function toEntity(
        StaffMemberModel $model,
        string $businessId,
        string $accountId,
        StaffRole $role,
    ): StaffMember {
        return StaffMember::restore(
            id: $model->uuid,
            businessId: $businessId,
            accountId: $accountId,
            role: $role,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * The membership fact alone: the role is written separately, by
     * StaffRoleAssignments.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(StaffMember $member, int $businessKey, int $accountKey): array
    {
        return [
            'uuid' => $member->id,
            'business_id' => $businessKey,
            'account_id' => $accountKey,
        ];
    }
}
