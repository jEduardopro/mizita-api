<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Eloquent;

use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\ServiceNameAlreadyTaken;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\ServiceSlugAlreadyTaken;
use App\Domains\Services\Infrastructure\Eloquent\Mappers\ServiceMapper;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Services\ValueObjects\ServiceQuery;
use App\Domains\Services\ValueObjects\ServiceSort;
use App\Shared\Infrastructure\Search\SearchableColumns;
use App\Shared\Infrastructure\Search\TokenSearch;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentServiceRepository implements ServiceRepository
{
    private const NAME_UNIQUE_INDEX = 'services_business_name_lower_unique';

    private const SLUG_UNIQUE_INDEX = 'services_business_slug_unique';

    private const BUSINESSES_TABLE = 'businesses';

    private const STAFF_MEMBERS_TABLE = 'staff_members';

    private const STAFF_SELECTION = 'staffMembers:id,uuid';

    private const TIEBREAKER_COLUMN = 'id';

    private const MAXIMUM_ACTIVE_SERVICES = 200;

    public function __construct(
        private readonly ServiceMapper $mapper,
        private readonly TokenSearch $tokenSearch,
    ) {}

    /**
     * @return Paginated<Service>
     */
    public function search(string $businessId, ServiceQuery $query): Paginated
    {
        $matching = $this->matching($businessId, $query->search);
        $total = $matching->count();

        $models = $this->mostRelevantFirst($matching, $query->search)
            ->with(self::STAFF_SELECTION)
            ->orderBy(self::columnFor($query->sort), $query->direction->value)
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->offset($query->pagination->offset())
            ->limit($query->pagination->perPage)
            ->get();

        return Paginated::of(
            array_map(
                fn (ServiceModel $model): Service => $this->mapper->toEntity($model, $businessId),
                $models->all(),
            ),
            $total,
            $query->pagination,
        );
    }

    /**
     * @return list<Service>
     */
    public function activeForBusiness(string $businessId): array
    {
        return $this->ofBusiness($businessId)
            ->with(self::STAFF_SELECTION)
            ->where('active', true)
            ->orderBy('name')
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->limit(self::MAXIMUM_ACTIVE_SERVICES)
            ->get()
            ->map(fn (ServiceModel $model): Service => $this->mapper->toEntity($model, $businessId))
            ->values()
            ->all();
    }

    public function findForBusiness(string $businessId, string $id): Service
    {
        $model = $this->ofBusiness($businessId)
            ->with(self::STAFF_SELECTION)
            ->where('uuid', $id)
            ->first();

        if ($model === null) {
            throw ServiceNotFound::withId($id);
        }

        return $this->mapper->toEntity($model, $businessId);
    }

    public function existsByName(string $businessId, string $name): bool
    {
        return $this->ofBusiness($businessId)
            ->whereRaw('lower(name) = lower(?)', [trim($name)])
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function slugsMatching(string $businessId, string $base): array
    {
        return $this->ofBusiness($businessId)
            ->where(function (Builder $query) use ($base): void {
                $query->where('slug', $base)
                    ->orWhere('slug', 'like', self::escapeLike($base).'-%');
            })
            ->pluck('slug')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function namesMatching(string $businessId, string $base): array
    {
        return $this->ofBusiness($businessId)
            ->where(function (Builder $query) use ($base): void {
                $query->whereRaw('lower(name) = lower(?)', [$base])
                    ->orWhereRaw('lower(name) like lower(?)', [self::escapeLike($base).' %']);
            })
            ->pluck('name')
            ->all();
    }

    public function save(Service $service): void
    {
        $businessKey = $this->businessKeyFor($service->businessId);

        try {
            $model = ServiceModel::query()->updateOrCreate(
                ['uuid' => $service->id],
                $this->mapper->toAttributes($service, $businessKey),
            );
        } catch (UniqueConstraintViolationException $violation) {
            $this->failFrom($service, $violation);
        }

        $model->staffMembers()->sync($this->staffKeys($service->staffIds(), $businessKey));
    }

    public function delete(string $businessId, string $id): void
    {
        $model = $this->ofBusiness($businessId)->where('uuid', $id)->first();

        if ($model === null) {
            throw ServiceNotFound::withId($id);
        }

        $model->delete();
    }

    /**
     * @return Builder<ServiceModel>
     */
    private function matching(string $businessId, ?SearchTerm $search): Builder
    {
        $query = $this->ofBusiness($businessId);

        if ($search === null) {
            return $query;
        }

        return $this->tokenSearch->apply($query, $search, self::searchableColumns());
    }

    /**
     * @param  Builder<ServiceModel>  $query
     * @return Builder<ServiceModel>
     */
    private function mostRelevantFirst(Builder $query, ?SearchTerm $search): Builder
    {
        if ($search === null) {
            return $query;
        }

        return $this->tokenSearch->orderByRelevance($query, $search, self::searchableColumns());
    }

    private static function searchableColumns(): SearchableColumns
    {
        return SearchableColumns::text('name', 'description')
            ->alsoMatchingNumeric('duration_minutes', 'price');
    }

    /**
     * @return Builder<ServiceModel>
     */
    private function ofBusiness(string $businessId): Builder
    {
        return ServiceModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }

    private function businessKeyFor(string $businessId): int
    {
        $key = DB::table(self::BUSINESSES_TABLE)->where('uuid', $businessId)->value('id');

        if ($key === null) {
            throw new RuntimeException("Business [{$businessId}] is not on record.");
        }

        return (int) $key;
    }

    /**
     * @param  list<string>  $staffIds
     * @return list<int>
     */
    private function staffKeys(array $staffIds, int $businessKey): array
    {
        if ($staffIds === []) {
            return [];
        }

        return DB::table(self::STAFF_MEMBERS_TABLE)
            ->where('business_id', $businessKey)
            ->whereNull('deleted_at')
            ->whereIn('uuid', $staffIds)
            ->pluck('id')
            ->map(static fn (mixed $key): int => (int) $key)
            ->all();
    }

    private static function columnFor(ServiceSort $sort): string
    {
        return match ($sort) {
            ServiceSort::Name => 'name',
            ServiceSort::Price => 'price',
            ServiceSort::Duration => 'duration_minutes',
            ServiceSort::CreatedAt => 'created_at',
        };
    }

    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    private function failFrom(Service $service, UniqueConstraintViolationException $violation): never
    {
        $message = $violation->getMessage();

        if (str_contains($message, self::NAME_UNIQUE_INDEX)) {
            throw ServiceNameAlreadyTaken::for($service->name(), $violation);
        }

        if (str_contains($message, self::SLUG_UNIQUE_INDEX)) {
            throw ServiceSlugAlreadyTaken::for($service->slug(), $violation);
        }

        throw $violation;
    }
}
