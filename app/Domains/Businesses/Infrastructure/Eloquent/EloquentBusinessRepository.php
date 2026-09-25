<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\InvalidBusinessOwner;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Infrastructure\Eloquent\Mappers\BusinessMapper;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentBusinessRepository implements BusinessRepository
{
    private const NAME_UNIQUE_INDEX = 'businesses_name_lower_unique';

    private const SLUG_UNIQUE_INDEX = 'businesses_slug_lower_unique';

    private const STORAGE_TIMEZONE = 'UTC';

    private const MAPPED_RELATIONS = ['industry', 'closedBy'];

    public function __construct(
        private readonly BusinessMapper $mapper,
    ) {}

    public function findById(string $id): Business
    {
        return $this->mapper->toEntity($this->modelOrFail($id));
    }

    public function findBySlug(string $slug): Business
    {
        $model = $this->matchingSlug($slug)->with(self::MAPPED_RELATIONS)->first();

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
            ->with(self::MAPPED_RELATIONS)
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

    public function findClosedById(string $id): ?Business
    {
        $model = $this->closed()->where('uuid', $id)->first();

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    public function findClosedOwnedBy(string $accountId): ?Business
    {
        $model = $this->closed()
            ->whereHas('closedBy', static function (Builder $account) use ($accountId): void {
                $account->where('uuid', $accountId);
            })
            ->latest('closed_at')
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    /**
     * @return list<string>
     */
    public function idsDueForPurge(DateTimeImmutable $cutoff): array
    {
        /** @var list<string> $ids */
        $ids = BusinessModel::withTrashed()
            ->whereNotNull('closed_at')
            ->whereNull('purged_at')
            ->where('closed_at', '<=', $cutoff->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE))->format(DATE_ATOM))
            ->orderBy('closed_at')
            ->pluck('uuid')
            ->all();

        return $ids;
    }

    public function existsByName(string $name): bool
    {
        return $this->reservingNamesAndSlugs()
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
        return $this->reservingNamesAndSlugs()
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
            BusinessModel::withTrashed()->updateOrCreate(
                ['uuid' => $business->id],
                $this->mapper->toAttributes(
                    $business,
                    $this->industryKeyFor($business),
                    $this->closedByAccountKeyFor($business),
                ),
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

    /**
     * @return Builder<BusinessModel>
     */
    private function closed(): Builder
    {
        return BusinessModel::withTrashed()
            ->with(self::MAPPED_RELATIONS)
            ->whereNotNull('closed_at');
    }

    /**
     * @return Builder<BusinessModel>
     */
    private function reservingNamesAndSlugs(): Builder
    {
        return BusinessModel::withTrashed()->where(static function (Builder $reserved): void {
            $reserved->whereNull('deleted_at')->orWhereNotNull('closed_at');
        });
    }

    private function modelOrFail(string $id): BusinessModel
    {
        $model = BusinessModel::query()
            ->with(self::MAPPED_RELATIONS)
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

    /**
     * @throws InvalidBusinessOwner
     */
    private function closedByAccountKeyFor(Business $business): ?int
    {
        $accountId = $business->closedByAccountId();

        if ($accountId === null) {
            return null;
        }

        $key = User::withTrashed()->where('uuid', $accountId)->value('id');

        if ($key === null) {
            throw InvalidBusinessOwner::unknownAccount($accountId);
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
