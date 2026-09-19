<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Eloquent;

use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Infrastructure\Eloquent\Mappers\CustomerMapper;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Domains\Customers\ValueObjects\CustomerQuery;
use App\Domains\Customers\ValueObjects\CustomerSort;
use App\Shared\Contracts\BusinessTeamKey;
use App\Shared\Infrastructure\Search\SearchableColumns;
use App\Shared\Infrastructure\Search\TokenSearch;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentCustomerRepository implements CustomerRepository
{
    private const EMAIL_UNIQUE_INDEX = 'customers_business_email_lower_unique';

    private const BUSINESSES_TABLE = 'businesses';

    private const TIEBREAKER_COLUMN = 'id';

    public function __construct(
        private readonly CustomerMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
        private readonly TokenSearch $tokenSearch,
    ) {}

    /**
     * @return Paginated<Customer>
     */
    public function search(string $businessId, CustomerQuery $query): Paginated
    {
        $matching = $this->matching($businessId, $query);
        $total = $matching->count();

        $models = $this->mostRelevantFirst($matching, $query->search)
            ->orderBy(self::columnFor($query->sort), $query->direction->value)
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->offset($query->pagination->offset())
            ->limit($query->pagination->perPage)
            ->get();

        return Paginated::of(
            array_map(
                fn (CustomerModel $model): Customer => $this->mapper->toEntity($model, $businessId),
                $models->all(),
            ),
            $total,
            $query->pagination,
        );
    }

    public function findForBusiness(string $businessId, string $id): Customer
    {
        return $this->firstMatchingUuid($this->ofBusiness($businessId), $businessId, $id);
    }

    public function findIncludingArchived(string $businessId, string $id): Customer
    {
        return $this->firstMatchingUuid($this->ofBusiness($businessId)->withTrashed(), $businessId, $id);
    }

    public function existsByEmail(string $businessId, CustomerEmail $email, ?string $exceptId = null): bool
    {
        return $this->excluding($this->ofBusiness($businessId), $exceptId)
            ->whereRaw('lower(email) = lower(?)', [$email->value])
            ->exists();
    }

    /**
     * @param  list<string>  $customerIds
     */
    public function existsAmong(string $businessId, array $customerIds, ?string $exceptId = null): bool
    {
        if ($customerIds === []) {
            return false;
        }

        return $this->excluding($this->ofBusiness($businessId), $exceptId)
            ->whereIn('uuid', $customerIds)
            ->exists();
    }

    public function findByEmail(string $businessId, CustomerEmail $email): ?Customer
    {
        $model = $this->ofBusiness($businessId)
            ->whereRaw('lower(email) = lower(?)', [$email->value])
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model, $businessId);
    }

    /**
     * @param  list<string>  $customerIds
     */
    public function findFirstAmong(string $businessId, array $customerIds): ?Customer
    {
        if ($customerIds === []) {
            return null;
        }

        $model = $this->ofBusiness($businessId)
            ->whereIn('uuid', $customerIds)
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model, $businessId);
    }

    public function save(Customer $customer): void
    {
        try {
            CustomerModel::query()->updateOrCreate(
                ['uuid' => $customer->id],
                $this->mapper->toAttributes(
                    $customer,
                    $this->businessKeys->teamKeyFor($customer->businessId),
                ),
            );
        } catch (UniqueConstraintViolationException $violation) {
            $this->failFrom($customer, $violation);
        }
    }

    public function delete(string $businessId, string $id): void
    {
        $model = $this->ofBusiness($businessId)->where('uuid', $id)->first();

        if ($model === null) {
            throw CustomerNotFound::withId($id);
        }

        $model->delete();
    }

    /**
     * @param  Builder<CustomerModel>  $scoped
     *
     * @throws CustomerNotFound
     */
    private function firstMatchingUuid(Builder $scoped, string $businessId, string $id): Customer
    {
        $model = $scoped->where('uuid', $id)->first();

        if ($model === null) {
            throw CustomerNotFound::withId($id);
        }

        return $this->mapper->toEntity($model, $businessId);
    }

    /**
     * @return Builder<CustomerModel>
     */
    private function matching(string $businessId, CustomerQuery $query): Builder
    {
        $scoped = $this->ofBusiness($businessId);
        $search = $query->search;

        if ($search === null) {
            return $scoped;
        }

        $phoneMatches = $query->phoneMatches;

        return $scoped->where(function (Builder $matches) use ($search, $phoneMatches): void {
            $this->tokenSearch->apply($matches, $search, self::searchableColumns());

            if ($phoneMatches === []) {
                return;
            }

            $matches->orWhereIn('uuid', $phoneMatches);
        });
    }

    /**
     * @param  Builder<CustomerModel>  $query
     * @return Builder<CustomerModel>
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
        return SearchableColumns::text('name', 'email');
    }

    /**
     * @return Builder<CustomerModel>
     */
    private function ofBusiness(string $businessId): Builder
    {
        return CustomerModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }

    /**
     * @param  Builder<CustomerModel>  $query
     * @return Builder<CustomerModel>
     */
    private function excluding(Builder $query, ?string $exceptId): Builder
    {
        if ($exceptId === null) {
            return $query;
        }

        return $query->where('uuid', '!=', $exceptId);
    }

    private static function columnFor(CustomerSort $sort): string
    {
        return match ($sort) {
            CustomerSort::Name => 'name',
            CustomerSort::CreatedAt => 'created_at',
        };
    }

    private function failFrom(Customer $customer, UniqueConstraintViolationException $violation): never
    {
        if (str_contains($violation->getMessage(), self::EMAIL_UNIQUE_INDEX)) {
            throw CustomerEmailAlreadyTaken::for((string) $customer->email()?->value, $violation);
        }

        throw $violation;
    }
}
