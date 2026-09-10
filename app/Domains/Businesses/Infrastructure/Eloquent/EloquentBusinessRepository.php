<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Infrastructure\Eloquent\Mappers\BusinessMapper;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;

final class EloquentBusinessRepository implements BusinessRepository
{
    public function __construct(
        private readonly BusinessMapper $mapper,
    ) {}

    public function findById(string $id): Business
    {
        return $this->mapper->toEntity($this->modelOrFail($id));
    }

    public function existsBySlug(string $slug): bool
    {
        return BusinessModel::query()->where('slug', $slug)->exists();
    }

    public function save(Business $business): void
    {
        BusinessModel::query()->updateOrCreate(
            ['uuid' => $business->id],
            $this->mapper->toAttributes($business),
        );
    }

    public function delete(string $id): void
    {
        $this->modelOrFail($id)->delete();
    }

    private function modelOrFail(string $id): BusinessModel
    {
        $model = BusinessModel::query()->where('uuid', $id)->first();

        if ($model === null) {
            throw BusinessNotFound::withId($id);
        }

        return $model;
    }
}
