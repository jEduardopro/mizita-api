<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Media;

use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Shared\Infrastructure\Media\SafeFileName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class SpatieServiceImages implements ServiceImages
{
    private const FALLBACK_FILE_NAME = 'image';

    private const BUSINESSES_TABLE = 'businesses';

    public function urlFor(string $businessId, string $serviceId): ?string
    {
        $model = self::ofBusiness($businessId)->with('media')->where('uuid', $serviceId)->first();

        if ($model === null) {
            return null;
        }

        return self::urlOrNull($model);
    }

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, string>
     */
    public function urlsFor(string $businessId, array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $urls = [];

        $models = self::ofBusiness($businessId)->with('media')->whereIn('uuid', $serviceIds)->get();

        foreach ($models as $model) {
            $url = self::urlOrNull($model);

            if ($url !== null) {
                $urls[$model->uuid] = $url;
            }
        }

        return $urls;
    }

    public function replace(string $businessId, string $serviceId, string $sourcePath, string $fileName): string
    {
        return $this->modelOrFail($businessId, $serviceId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(ServiceModel::IMAGE_COLLECTION)
            ->getUrl();
    }

    public function remove(string $businessId, string $serviceId): void
    {
        $this->modelOrFail($businessId, $serviceId)->clearMediaCollection(ServiceModel::IMAGE_COLLECTION);
    }

    public function copy(string $businessId, string $sourceServiceId, string $targetServiceId): void
    {
        $source = $this->modelOrFail($businessId, $sourceServiceId);
        $target = $this->modelOrFail($businessId, $targetServiceId);

        $image = $source->getFirstMedia(ServiceModel::IMAGE_COLLECTION);

        if ($image === null) {
            return;
        }

        $image->copy($target, ServiceModel::IMAGE_COLLECTION);
    }

    private function modelOrFail(string $businessId, string $serviceId): ServiceModel
    {
        $model = self::ofBusiness($businessId)->where('uuid', $serviceId)->first();

        if ($model === null) {
            throw ServiceNotFound::withId($serviceId);
        }

        return $model;
    }

    /**
     * @return Builder<ServiceModel>
     */
    private static function ofBusiness(string $businessId): Builder
    {
        return ServiceModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }

    private static function urlOrNull(ServiceModel $model): ?string
    {
        $url = $model->getFirstMediaUrl(ServiceModel::IMAGE_COLLECTION);

        return $url === '' ? null : $url;
    }
}
