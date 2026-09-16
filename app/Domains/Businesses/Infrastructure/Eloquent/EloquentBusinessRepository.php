<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Infrastructure\Eloquent\Mappers\BusinessMapper;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentBusinessRepository implements BusinessRepository
{
    private const NAME_UNIQUE_INDEX = 'businesses_name_lower_unique';

    private const SLUG_UNIQUE_INDEX = 'businesses_slug_lower_unique';

    public function __construct(
        private readonly BusinessMapper $mapper,
    ) {}

    public function findById(string $id): Business
    {
        return $this->mapper->toEntity($this->modelOrFail($id));
    }

    public function findBySlug(string $slug): Business
    {
        $model = $this->matchingSlug($slug)->with('industry')->first();

        if ($model === null) {
            throw BusinessNotFound::withSlug($slug);
        }

        return $this->mapper->toEntity($model);
    }

    /**
     * @param  list<string>  $ids
     * @return list<Business>
     */
    public function findManyByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $modelsById = BusinessModel::query()
            ->with('industry')
            ->whereIn('uuid', $ids)
            ->get()
            ->keyBy('uuid');

        $businesses = [];

        foreach ($ids as $id) {
            $model = $modelsById->get($id);

            if ($model instanceof BusinessModel) {
                $businesses[] = $this->mapper->toEntity($model);
            }
        }

        return $businesses;
    }

    public function existsByName(string $name): bool
    {
        return BusinessModel::query()
            ->whereRaw('lower(name) = lower(?)', [trim($name)])
            ->exists();
    }

    public function existsBySlug(string $slug): bool
    {
        return $this->matchingSlug($slug)->exists();
    }

    /**
     * @return list<string>
     */
    public function slugsMatching(string $base): array
    {
        return BusinessModel::query()
            ->where(function (Builder $query) use ($base): void {
                $query->where('slug', $base)
                    ->orWhere('slug', 'like', $base.'-%');
            })
            ->pluck('slug')
            ->all();
    }

    public function save(Business $business): void
    {
        try {
            BusinessModel::query()->updateOrCreate(
                ['uuid' => $business->id],
                $this->mapper->toAttributes($business, $this->industryKeyFor($business)),
            );
        } catch (UniqueConstraintViolationException $violation) {
            $this->failFrom($business, $violation);
        }
    }

    public function delete(string $id): void
    {
        $this->modelOrFail($id)->delete();
    }

    /**
     * @return Builder<BusinessModel>
     */
    private function matchingSlug(string $slug): Builder
    {
        return BusinessModel::query()->whereRaw('lower(slug) = ?', [mb_strtolower(trim($slug))]);
    }

    private function modelOrFail(string $id): BusinessModel
    {
        $model = BusinessModel::query()
            ->with('industry')
            ->where('uuid', $id)
            ->first();

        if ($model === null) {
            throw BusinessNotFound::withId($id);
        }

        return $model;
    }

    private function industryKeyFor(Business $business): int
    {
        $key = IndustryModel::query()
            ->where('uuid', $business->industryId())
            ->value('id');

        if ($key === null) {
            throw UnknownIndustry::withId($business->industryId());
        }

        return (int) $key;
    }

    private function failFrom(Business $business, UniqueConstraintViolationException $violation): never
    {
        $message = $violation->getMessage();

        if (str_contains($message, self::NAME_UNIQUE_INDEX)) {
            throw BusinessNameAlreadyTaken::for($business->name(), $violation);
        }

        if (str_contains($message, self::SLUG_UNIQUE_INDEX)) {
            throw BusinessSlugAlreadyTaken::for($business->slug(), $violation);
        }

        throw $violation;
    }
}
