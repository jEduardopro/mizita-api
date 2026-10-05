<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Media;

use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Shared\Infrastructure\Media\OptimizedImageUrl;
use App\Shared\Infrastructure\Media\SafeFileName;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SpatieBusinessLogo implements BusinessLogo
{
    private const FALLBACK_FILE_NAME = 'logo';

    public function urlFor(string $businessId): ?string
    {
        $logo = self::logoOf($businessId);

        return $logo === null ? null : OptimizedImageUrl::of($logo);
    }

    public function originalUrlFor(string $businessId): ?string
    {
        return self::logoOf($businessId)?->getUrl();
    }

    public function replace(string $businessId, string $sourcePath, string $fileName): string
    {
        $logo = $this->modelOrFail($businessId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($sourcePath, $fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(BusinessModel::LOGO_COLLECTION);

        return OptimizedImageUrl::of($logo);
    }

    public function remove(string $businessId): void
    {
        $this->modelOrFail($businessId)->clearMediaCollection(BusinessModel::LOGO_COLLECTION);
    }

    private static function logoOf(string $businessId): ?Media
    {
        return BusinessModel::query()
            ->with('media')
            ->where('uuid', $businessId)
            ->first()
            ?->getFirstMedia(BusinessModel::LOGO_COLLECTION);
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
