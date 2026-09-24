<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent\Mappers;

use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;
use DateTimeImmutable;

final class StaffProfileMapper
{
    public function toEntity(StaffProfileModel $model, string $businessId, string $staffMemberId): StaffProfile
    {
        return StaffProfile::restore(
            id: $model->uuid,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            jobTitle: $model->job_title === null ? null : JobTitle::restore($model->job_title),
            about: $model->about === null ? null : About::restore($model->about),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(StaffProfile $profile, int $businessKey, int $staffMemberKey): array
    {
        return [
            'uuid' => $profile->id,
            'business_id' => $businessKey,
            'staff_member_id' => $staffMemberKey,
            'job_title' => $profile->jobTitle()?->value,
            'about' => $profile->about()?->value,
        ];
    }
}
