<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Media;

use App\Domains\Customers\Contracts\CustomerPhotos;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Shared\Infrastructure\Media\SafeFileName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class SpatieCustomerPhotos implements CustomerPhotos
{
    private const FALLBACK_FILE_NAME = 'photo';

    private const BUSINESSES_TABLE = 'businesses';

    public function urlFor(string $businessId, string $customerId): ?string
    {
        $model = self::ofBusiness($businessId)->with('media')->where('uuid', $customerId)->first();

        if ($model === null) {
            return null;
        }

        return self::urlOrNull($model);
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, string>
     */
    public function urlsFor(string $businessId, array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $urls = [];

        $models = self::ofBusiness($businessId)->with('media')->whereIn('uuid', $customerIds)->get();

        foreach ($models as $model) {
            $url = self::urlOrNull($model);

            if ($url !== null) {
                $urls[$model->uuid] = $url;
            }
        }

        return $urls;
    }

    public function replace(string $businessId, string $customerId, string $sourcePath, string $fileName): void
    {
        $this->modelOrFail($businessId, $customerId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(CustomerModel::PHOTO_COLLECTION);
    }

    public function remove(string $businessId, string $customerId): void
    {
        $this->modelOrFail($businessId, $customerId)
            ->clearMediaCollection(CustomerModel::PHOTO_COLLECTION);
    }

    private function modelOrFail(string $businessId, string $customerId): CustomerModel
    {
        $model = self::ofBusiness($businessId)->where('uuid', $customerId)->first();

        if ($model === null) {
            throw CustomerNotFound::withId($customerId);
        }

        return $model;
    }

    /**
     * @return Builder<CustomerModel>
     */
    private static function ofBusiness(string $businessId): Builder
    {
        return CustomerModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }

    private static function urlOrNull(CustomerModel $model): ?string
    {
        $url = $model->getFirstMediaUrl(CustomerModel::PHOTO_COLLECTION);

        return $url === '' ? null : $url;
    }
}
