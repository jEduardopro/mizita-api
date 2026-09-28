<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\SubscriptionPeriodOverlaps;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers\SubscriptionMapper;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Contracts\BusinessTeamKey;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;

final class EloquentSubscriptionRepository implements SubscriptionRepository
{
    private const OVERLAP_SQLSTATE = '23P01';

    private const BUSINESSES_TABLE = 'businesses';

    private const BUSINESS_RELATION = 'business';

    private const TIEBREAKER_COLUMN = 'id';

    public function __construct(
        private readonly SubscriptionMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function save(Subscription $subscription): void
    {
        $attributes = $this->mapper->toAttributes(
            $subscription,
            $this->businessKeys->teamKeyFor($subscription->businessId),
        );

        try {
            SubscriptionModel::query()->updateOrCreate(['uuid' => $subscription->id], $attributes);
        } catch (QueryException $violation) {
            $this->failFrom($violation);
        }
    }

    public function inEffectFor(string $businessId, DateTimeImmutable $now): ?Subscription
    {
        $model = $this->inEffectAt($this->ofBusinesses([$businessId]), $now)
            ->orderByDesc('starts_at')
            ->orderByDesc(self::TIEBREAKER_COLUMN)
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model, $businessId);
    }

    /**
     * @param  list<string>  $businessIds
     * @return array<string, Subscription>
     */
    public function inEffectForMany(array $businessIds, DateTimeImmutable $now): array
    {
        if ($businessIds === []) {
            return [];
        }

        $models = $this->inEffectAt($this->ofBusinesses($businessIds), $now)
            ->with(self::BUSINESS_RELATION)
            ->orderBy('starts_at')
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->get();

        $inEffect = [];

        foreach ($models as $model) {
            $subscription = $this->toEntityWithItsBusiness($model);
            $inEffect[$subscription->businessId] = $subscription;
        }

        return $inEffect;
    }

    /**
     * @return list<Subscription>
     */
    public function dueForExpiry(DateTimeImmutable $now): array
    {
        $models = SubscriptionModel::query()
            ->with(self::BUSINESS_RELATION)
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now->format(DATE_ATOM))
            ->orderBy('ends_at')
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->get();

        return array_values(array_map(
            fn (SubscriptionModel $model): Subscription => $this->toEntityWithItsBusiness($model),
            $models->all(),
        ));
    }

    /**
     * @return list<Subscription>
     */
    public function historyOf(string $businessId): array
    {
        $models = $this->ofBusinesses([$businessId])
            ->orderByDesc('starts_at')
            ->orderByDesc(self::TIEBREAKER_COLUMN)
            ->get();

        return array_values(array_map(
            fn (SubscriptionModel $model): Subscription => $this->mapper->toEntity($model, $businessId),
            $models->all(),
        ));
    }

    /**
     * @param  list<string>  $businessIds
     * @return Builder<SubscriptionModel>
     */
    private function ofBusinesses(array $businessIds): Builder
    {
        return SubscriptionModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $businesses) => $businesses
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->whereIn('uuid', $businessIds),
        );
    }

    /**
     * @param  Builder<SubscriptionModel>  $query
     * @return Builder<SubscriptionModel>
     */
    private function inEffectAt(Builder $query, DateTimeImmutable $now): Builder
    {
        $instant = $now->format(DATE_ATOM);

        return $query
            ->where('status', SubscriptionStatus::Active->value)
            ->where('starts_at', '<=', $instant)
            ->where(static fn (Builder $open) => $open
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $instant));
    }

    private function toEntityWithItsBusiness(SubscriptionModel $model): Subscription
    {
        return $this->mapper->toEntity($model, $model->business->uuid);
    }

    /**
     * @throws SubscriptionPeriodOverlaps
     */
    private function failFrom(QueryException $violation): never
    {
        if ((string) $violation->getCode() === self::OVERLAP_SQLSTATE) {
            throw SubscriptionPeriodOverlaps::withAnotherPeriod($violation);
        }

        throw $violation;
    }
}
