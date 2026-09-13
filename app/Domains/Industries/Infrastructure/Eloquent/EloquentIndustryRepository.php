<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Eloquent;

use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Exceptions\IndustryNotFound;
use App\Domains\Industries\Infrastructure\Eloquent\Mappers\IndustryMapper;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;

final class EloquentIndustryRepository implements IndustryRepository
{
    public function __construct(
        private readonly IndustryMapper $mapper,
    ) {}

    /**
     * @return list<Industry>
     */
    public function allActive(): array
    {
        return IndustryModel::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('key')
            ->get()
            ->map(fn (IndustryModel $model): Industry => $this->mapper->toEntity($model))
            ->values()
            ->all();
    }

    public function existsById(string $id): bool
    {
        return IndustryModel::query()->where('uuid', $id)->exists();
    }

    public function isSelectable(string $id): bool
    {
        return IndustryModel::query()
            ->where('uuid', $id)
            ->where('active', true)
            ->exists();
    }

    public function findById(string $id): Industry
    {
        $model = IndustryModel::query()->where('uuid', $id)->first();

        if ($model === null) {
            throw IndustryNotFound::withId($id);
        }

        return $this->mapper->toEntity($model);
    }
}
