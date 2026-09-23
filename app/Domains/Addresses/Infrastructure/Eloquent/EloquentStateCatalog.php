<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent;

use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\Addresses\Entities\State;
use App\Domains\Addresses\Infrastructure\Eloquent\Mappers\StateMapper;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Shared\ValueObjects\CountryCode;
use Illuminate\Support\Str;

final class EloquentStateCatalog implements StateCatalog
{
    private const WHITESPACE_RUN = '/\s+/u';

    private const SINGLE_SPACE = ' ';

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

    public function findActiveByNameOrCode(CountryCode $country, string $nameOrCode): ?State
    {
        $wanted = self::comparable($nameOrCode);

        if ($wanted === '') {
            return null;
        }

        foreach ($this->allActiveFor($country) as $state) {
            if (self::comparable($state->name()) === $wanted || self::comparable($state->code()) === $wanted) {
                return $state;
            }
        }

        return null;
    }

    private static function comparable(string $value): string
    {
        $collapsed = (string) preg_replace(self::WHITESPACE_RUN, self::SINGLE_SPACE, trim($value));

        return Str::ascii(mb_strtolower($collapsed));
    }
}
