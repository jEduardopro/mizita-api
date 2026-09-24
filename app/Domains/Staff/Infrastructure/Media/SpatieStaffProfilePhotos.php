<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Media;

use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Exceptions\StaffProfileNotFound;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Shared\Infrastructure\Media\SafeFileName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class SpatieStaffProfilePhotos implements StaffProfilePhotos
{
    private const FALLBACK_FILE_NAME = 'photo';

    private const BUSINESSES_TABLE = 'businesses';

    public function urlFor(string $businessId, string $profileId): ?string
    {
        $model = self::ofBusiness($businessId)->with('media')->where('uuid', $profileId)->first();

        if ($model === null) {
            return null;
        }

        $url = $model->getFirstMediaUrl(StaffProfileModel::PHOTO_COLLECTION);

        return $url === '' ? null : $url;
    }

    public function replace(string $businessId, string $profileId, string $sourcePath, string $fileName): void
    {
        $this->modelOrFail($businessId, $profileId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(StaffProfileModel::PHOTO_COLLECTION);
    }

    public function remove(string $businessId, string $profileId): void
    {
        $this->modelOrFail($businessId, $profileId)
            ->clearMediaCollection(StaffProfileModel::PHOTO_COLLECTION);
    }

    private function modelOrFail(string $businessId, string $profileId): StaffProfileModel
    {
        $model = self::ofBusiness($businessId)->where('uuid', $profileId)->first();

        if ($model === null) {
            throw StaffProfileNotFound::withId($profileId);
        }

        return $model;
    }

    /**
     * @return Builder<StaffProfileModel>
     */
    private static function ofBusiness(string $businessId): Builder
    {
        return StaffProfileModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }
}
