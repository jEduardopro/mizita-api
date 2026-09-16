<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Media;

use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Shared\Infrastructure\Media\SafeFileName;

final class SpatieBusinessLogo implements BusinessLogo
{
    private const FALLBACK_FILE_NAME = 'logo';

    public function urlFor(string $businessId): ?string
    {
        $model = BusinessModel::query()->with('media')->where('uuid', $businessId)->first();

        if ($model === null) {
            return null;
        }

        $url = $model->getFirstMediaUrl(BusinessModel::LOGO_COLLECTION);

        return $url === '' ? null : $url;
    }

    public function replace(string $businessId, string $sourcePath, string $fileName): string
    {
        return $this->modelOrFail($businessId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(BusinessModel::LOGO_COLLECTION)
            ->getUrl();
    }

    public function remove(string $businessId): void
    {
        $this->modelOrFail($businessId)->clearMediaCollection(BusinessModel::LOGO_COLLECTION);
    }

    private function modelOrFail(string $businessId): BusinessModel
    {
        $model = BusinessModel::query()->where('uuid', $businessId)->first();

        if ($model === null) {
            throw BusinessNotFound::withId($businessId);
        }

        return $model;
    }
}
