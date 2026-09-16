<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent;

use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\Addresses\Entities\State;
use App\Domains\Addresses\Infrastructure\Eloquent\Mappers\StateMapper;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Shared\ValueObjects\CountryCode;

final class EloquentStateCatalog implements StateCatalog
{
    public function __construct(
        private readonly StateMapper $mapper,
    ) {}

    /**
     * @return list<State>
     */
    public function allActiveFor(CountryCode $country): array
    {
        return StateModel::query()
            ->where('country_code', $country->value)
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (StateModel $model): State => $this->mapper->toEntity($model))
            ->values()
            ->all();
    }
}
