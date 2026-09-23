<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent;

use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\Addresses\Entities\State;
use App\Domains\Addresses\Infrastructure\Eloquent\Mappers\StateMapper;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Domains\Addresses\Services\StateMatcher;
use App\Shared\ValueObjects\CountryCode;

final class EloquentStateCatalog implements StateCatalog
{
    public function __construct(
        private readonly StateMapper $mapper,
        private readonly StateMatcher $matcher,
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

    public function findActiveByNameOrCodePreferring(CountryCode $preferredCountry, string $nameOrCode): ?State
    {
        if (! $this->matcher->canMatch($nameOrCode)) {
            return null;
        }

        return $this->matcher->bestMatch($this->allActiveInCatalog(), $preferredCountry, $nameOrCode);
    }

    /**
     * @return list<State>
     */
    private function allActiveInCatalog(): array
    {
        return StateModel::query()
            ->whereIn('country_code', array_column(CountryCode::cases(), 'value'))
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (StateModel $model): State => $this->mapper->toEntity($model))
            ->values()
            ->all();
    }
}
