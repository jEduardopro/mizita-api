<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Media;

use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;

final class SpatieServiceImages implements ServiceImages
{
    private const FALLBACK_FILE_NAME = 'image';

    private const MAXIMUM_FILE_NAME_LENGTH = 80;

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
            ->usingFileName(self::safeFileName($fileName))
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

    private static function safeFileName(string $fileName): string
    {
        $stem = self::slugged((string) pathinfo($fileName, PATHINFO_FILENAME));
        $extension = self::slugged((string) pathinfo($fileName, PATHINFO_EXTENSION));

        $name = mb_substr($stem === '' ? self::FALLBACK_FILE_NAME : $stem, 0, self::MAXIMUM_FILE_NAME_LENGTH);

        return $extension === '' ? $name : $name.'.'.$extension;
    }

    private static function slugged(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($value)), '-');
    }
}
