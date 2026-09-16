<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Media;

use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Shared\Infrastructure\Media\SafeFileName;

final class SpatieServiceImages implements ServiceImages
{
    private const FALLBACK_FILE_NAME = 'image';

    public function urlFor(string $serviceId): ?string
    {
        $model = ServiceModel::query()->with('media')->where('uuid', $serviceId)->first();

        if ($model === null) {
            return null;
        }

        return self::urlOrNull($model);
    }

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, string>
     */
    public function urlsFor(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $urls = [];

        $models = ServiceModel::query()->with('media')->whereIn('uuid', $serviceIds)->get();

        foreach ($models as $model) {
            $url = self::urlOrNull($model);

            if ($url !== null) {
                $urls[$model->uuid] = $url;
            }
        }

        return $urls;
    }

    public function replace(string $serviceId, string $sourcePath, string $fileName): string
    {
        return $this->modelOrFail($serviceId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(ServiceModel::IMAGE_COLLECTION)
            ->getUrl();
    }

    public function remove(string $serviceId): void
    {
        $this->modelOrFail($serviceId)->clearMediaCollection(ServiceModel::IMAGE_COLLECTION);
    }

    public function copy(string $sourceServiceId, string $targetServiceId): void
    {
        $image = $this->modelOrFail($sourceServiceId)->getFirstMedia(ServiceModel::IMAGE_COLLECTION);

        if ($image === null) {
            return;
        }

        $image->copy($this->modelOrFail($targetServiceId), ServiceModel::IMAGE_COLLECTION);
    }

    private function modelOrFail(string $serviceId): ServiceModel
    {
        $model = ServiceModel::query()->where('uuid', $serviceId)->first();

        if ($model === null) {
            throw ServiceNotFound::withId($serviceId);
        }

        return $model;
    }

    private static function urlOrNull(ServiceModel $model): ?string
    {
        $url = $model->getFirstMediaUrl(ServiceModel::IMAGE_COLLECTION);

        return $url === '' ? null : $url;
    }
}
