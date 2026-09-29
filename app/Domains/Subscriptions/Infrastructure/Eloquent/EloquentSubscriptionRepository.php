<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\CheckoutAlreadyStarted;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers\SubscriptionMapper;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Contracts\BusinessTeamKey;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;

final class EloquentSubscriptionRepository implements SubscriptionRepository
{
    private const UNIQUE_VIOLATION_SQLSTATE = '23505';

    private const ONE_PER_BUSINESS_INDEX = 'subscriptions_business_unique_when_not_deleted';

    private const BUSINESSES_TABLE = 'businesses';

    private const RELATIONS = ['business', 'plan'];

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
            $this->planKeyFor($subscription->planId()),
        );

        try {
            SubscriptionModel::query()->updateOrCreate(['uuid' => $subscription->id], $attributes);
        } catch (QueryException $violation) {
            $this->failFrom($violation, $subscription->businessId);
        }
    }

    public function forBusiness(string $businessId): ?Subscription
    {
        $model = $this->ofBusinesses([$businessId])->with(self::RELATIONS)->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function forBillingCustomer(string $billingCustomerId): ?Subscription
    {
        $model = SubscriptionModel::query()
            ->with(self::RELATIONS)
            ->where('stripe_customer_id', $billingCustomerId)
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    /**
     * @param  list<string>  $businessIds
     * @return array<string, Subscription>
     */
    public function forManyBusinesses(array $businessIds): array
    {
        if ($businessIds === []) {
            return [];
        }

        $subscriptions = [];

        foreach ($this->ofBusinesses($businessIds)->with(self::RELATIONS)->get() as $model) {
            $subscription = $this->toEntity($model);
            $subscriptions[$subscription->businessId] = $subscription;
        }

        return $subscriptions;
    }

    /**
     * @return list<Subscription>
     */
    public function pastPaymentGrace(DateTimeImmutable $now): array
    {
        $cutoff = $now->sub(new DateInterval(Subscription::PAYMENT_GRACE));

        return $this->listOf(SubscriptionModel::query()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('stripe_subscription_id')
            ->where('payment_failed_at', '<=', $cutoff->format(DATE_ATOM))
            ->orderBy('payment_failed_at'));
    }

    /**
     * @return list<Subscription>
     */
    public function lapsedWithoutEnding(DateTimeImmutable $now): array
    {
        $instant = $now->format(DATE_ATOM);
        $renewalCutoff = $now->sub(new DateInterval(Subscription::RENEWAL_LEEWAY))->format(DATE_ATOM);

        return $this->listOf(SubscriptionModel::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->whereNotNull('stripe_subscription_id')
            ->where(static fn (Builder $lapsed) => $lapsed
                ->where(static fn (Builder $renewing) => $renewing
                    ->whereNull('canceled_at')
                    ->where('current_period_ends_at', '<=', $renewalCutoff))
                ->orWhere(static fn (Builder $ending) => $ending
                    ->whereNotNull('canceled_at')
                    ->where('current_period_ends_at', '<=', $instant)))
            ->orderBy('current_period_ends_at'));
    }

    /**
     * @param  Builder<SubscriptionModel>  $query
     * @return list<Subscription>
     */
    private function listOf(Builder $query): array
    {
        $models = $query->with(self::RELATIONS)->orderBy(self::TIEBREAKER_COLUMN)->get();

        return array_values(array_map(
            fn (SubscriptionModel $model): Subscription => $this->toEntity($model),
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

    private function planKeyFor(string $planId): int
    {
        return (int) PlanModel::withTrashed()->where('uuid', $planId)->valueOrFail('id');
    }

    private function toEntity(SubscriptionModel $model): Subscription
    {
        return $this->mapper->toEntity($model, $model->business->uuid);
    }

    /**
     * @throws CheckoutAlreadyStarted
     */
    private function failFrom(QueryException $violation, string $businessId): never
    {
        if ((string) $violation->getCode() === self::UNIQUE_VIOLATION_SQLSTATE
            && str_contains($violation->getMessage(), self::ONE_PER_BUSINESS_INDEX)) {
            throw CheckoutAlreadyStarted::forBusiness($businessId, $violation);
        }

        throw $violation;
    }
}
