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
    /** The partial unique indexes from the businesses migration; if either name changes there, it changes here. */
    private const NAME_UNIQUE_INDEX = 'businesses_name_lower_unique';

    private const SLUG_UNIQUE_INDEX = 'businesses_slug_lower_unique';

    public function __construct(
        private readonly BusinessMapper $mapper,
    ) {}

    public function findById(string $id): Business
    {
        return $this->mapper->toEntity($this->modelOrFail($id));
    }

    public function existsByName(string $name): bool
    {
        return BusinessModel::query()
            ->whereRaw('lower(name) = lower(?)', [trim($name)])
            ->exists();
    }

    /**
     * The LIKE pattern is not escaped, and that is a decision: a base only ever
     * arrives here from Slug, whose alphabet is [a-z0-9-], so there is no
     * wildcard to neutralise. A caller that builds a base some other way breaks
     * that invariant and this query with it.
     *
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
            // Knowing what a unique index is stops here. Letting an Illuminate
            // exception past this boundary would break the layer rule and make
            // every caller untestable without the framework.
            $this->failFrom($business, $violation);
        }
    }

    public function delete(string $id): void
    {
        $this->modelOrFail($id)->delete();
    }

    private function modelOrFail(string $id): BusinessModel
    {
        $model = BusinessModel::query()
            // Eager loaded because the mapper reads the industry's uuid from
            // it. A use case never knows this happened.
            ->with('industry')
            ->where('uuid', $id)
            ->first();

        if ($model === null) {
            throw BusinessNotFound::withId($id);
        }

        return $model;
    }

    /**
     * Reaching for the neighbour's model is allowed here and nowhere else:
     * translating a public identity into a private one is exactly the work an
     * adapter exists to do.
     */
    private function industryKeyFor(Business $business): int
    {
        $key = IndustryModel::query()
            ->where('uuid', $business->industryId)
            ->value('id');

        if ($key === null) {
            throw UnknownIndustry::withId($business->industryId);
        }

        return (int) $key;
    }

    /**
     * An unrecognised index is not translated: it means a constraint nobody
     * modelled fired, and dressing that up as a name conflict would tell the
     * caller something untrue while hiding the real defect.
     */
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
