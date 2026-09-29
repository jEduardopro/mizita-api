<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers\SubscriptionMapper;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const SUBSCRIPTION_MAPPER_BUSINESS_KEY = 42;

const SUBSCRIPTION_MAPPER_PLAN_KEY = 3;

function subscriptionPlanRow(string $uuid = SubscriptionFixtures::PLAN_ID, string $key = 'complete'): PlanModel
{
    $plan = new PlanModel;

    $plan->setRawAttributes(['id' => SUBSCRIPTION_MAPPER_PLAN_KEY, 'uuid' => $uuid, 'key' => $key], true);

    return $plan;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function subscriptionRow(array $overrides = []): SubscriptionModel
{
    $model = new SubscriptionModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => SubscriptionFixtures::SUBSCRIPTION_ID,
        'business_id' => SUBSCRIPTION_MAPPER_BUSINESS_KEY,
        'plan_id' => SUBSCRIPTION_MAPPER_PLAN_KEY,
        'status' => 'past_due',
        'stripe_customer_id' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
        'stripe_subscription_id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
        'started_at' => '2026-06-01 15:00:00+00',
        'current_period_ends_at' => '2026-07-01 15:00:00+00',
        'canceled_at' => '2026-06-10 15:00:00+00',
        'payment_failed_at' => '2026-06-14 09:30:00+00',
        'created_at' => SubscriptionFixtures::instant(SubscriptionFixtures::CREATED_AT),
        ...$overrides,
    ], true);
    $model->setRelation('plan', subscriptionPlanRow());

    return $model;
}

function roundTripped(Subscription $subscription): Subscription
{
    $mapper = new SubscriptionMapper;
    $model = new SubscriptionModel;

    $model->fill($mapper->toAttributes($subscription, SUBSCRIPTION_MAPPER_BUSINESS_KEY, SUBSCRIPTION_MAPPER_PLAN_KEY));
    $model->setRawAttributes([...$model->getAttributes(), 'created_at' => $subscription->createdAt], true);
    $model->setRelation('plan', subscriptionPlanRow($subscription->planId(), $subscription->plan()->value));

    return $mapper->toEntity($model, $subscription->businessId);
}

describe('reading a row', function () {
    it('restores every value the row carries', function () {
        $subscription = (new SubscriptionMapper)->toEntity(subscriptionRow(), SubscriptionFixtures::BUSINESS_ID);

        expect($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($subscription->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($subscription->billingCustomerId)->toBe(SubscriptionFixtures::BILLING_CUSTOMER_ID)
            ->and($subscription->planId())->toBe(SubscriptionFixtures::PLAN_ID)
            ->and($subscription->plan())->toBe(Plan::Complete)
            ->and($subscription->status())->toBe(SubscriptionStatus::PastDue)
            ->and($subscription->billingSubscriptionId())->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID)
            ->and($subscription->startedAt()?->format(DATE_ATOM))->toBe('2026-06-01T15:00:00+00:00')
            ->and($subscription->currentPeriodEndsAt()?->format(DATE_ATOM))->toBe('2026-07-01T15:00:00+00:00')
            ->and($subscription->canceledAt()?->format(DATE_ATOM))->toBe('2026-06-10T15:00:00+00:00')
            ->and($subscription->paymentFailedAt()?->format(DATE_ATOM))->toBe('2026-06-14T09:30:00+00:00')
            ->and($subscription->createdAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::CREATED_AT));
    });

    it('carries the uuids it was handed and read off the plan, never the keys the row holds', function () {
        $subscription = (new SubscriptionMapper)->toEntity(subscriptionRow(), SubscriptionFixtures::BUSINESS_ID);

        expect($subscription->businessId)->not->toBe((string) SUBSCRIPTION_MAPPER_BUSINESS_KEY)
            ->and($subscription->planId())->not->toBe((string) SUBSCRIPTION_MAPPER_PLAN_KEY)
            ->and($subscription->id)->not->toBe('7');
    });

    it('reads the instants in UTC whatever offset the row was stored with', function () {
        $subscription = (new SubscriptionMapper)->toEntity(
            subscriptionRow(['current_period_ends_at' => '2026-07-01 17:00:00+02']),
            SubscriptionFixtures::BUSINESS_ID,
        );

        expect($subscription->currentPeriodEndsAt()?->format(DATE_ATOM))->toBe('2026-07-01T15:00:00+00:00')
            ->and($subscription->currentPeriodEndsAt()?->getTimezone()->getName())->toBe('UTC');
    });

    it('restores a row whose checkout never completed, with every optional column empty', function () {
        $subscription = (new SubscriptionMapper)->toEntity(subscriptionRow([
            'status' => 'incomplete',
            'stripe_subscription_id' => null,
            'started_at' => null,
            'current_period_ends_at' => null,
            'canceled_at' => null,
            'payment_failed_at' => null,
        ]), SubscriptionFixtures::BUSINESS_ID);

        expect($subscription->status())->toBe(SubscriptionStatus::Incomplete)
            ->and($subscription->billingSubscriptionId())->toBeNull()
            ->and($subscription->startedAt())->toBeNull()
            ->and($subscription->currentPeriodEndsAt())->toBeNull()
            ->and($subscription->canceledAt())->toBeNull()
            ->and($subscription->paymentFailedAt())->toBeNull();
    });

    it('restores every status the billing provider can report', function (SubscriptionStatus $status) {
        expect((new SubscriptionMapper)->toEntity(subscriptionRow(['status' => $status->value]), SubscriptionFixtures::BUSINESS_ID)->status())
            ->toBe($status);
    })->with(SubscriptionStatus::cases());
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        $attributes = (new SubscriptionMapper)->toAttributes(
            SubscriptionFixtures::subscription(
                status: SubscriptionStatus::PastDue,
                canceledAt: '2026-06-10T15:00:00+00:00',
                paymentFailedAt: '2026-06-14T09:30:00+00:00',
            ),
            SUBSCRIPTION_MAPPER_BUSINESS_KEY,
            SUBSCRIPTION_MAPPER_PLAN_KEY,
        );

        expect($attributes)->toEqual([
            'uuid' => SubscriptionFixtures::SUBSCRIPTION_ID,
            'business_id' => SUBSCRIPTION_MAPPER_BUSINESS_KEY,
            'plan_id' => SUBSCRIPTION_MAPPER_PLAN_KEY,
            'status' => 'past_due',
            'stripe_customer_id' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
            'stripe_subscription_id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            'started_at' => SubscriptionFixtures::instant(SubscriptionFixtures::STARTED_AT),
            'current_period_ends_at' => SubscriptionFixtures::instant(SubscriptionFixtures::PERIOD_ENDS_AT),
            'canceled_at' => SubscriptionFixtures::instant('2026-06-10T15:00:00+00:00'),
            'payment_failed_at' => SubscriptionFixtures::instant('2026-06-14T09:30:00+00:00'),
        ]);
    });

    it('writes the business and the plan as the keys it was handed, never as the uuids the entity carries', function () {
        $attributes = (new SubscriptionMapper)->toAttributes(
            SubscriptionFixtures::subscription(),
            SUBSCRIPTION_MAPPER_BUSINESS_KEY,
            SUBSCRIPTION_MAPPER_PLAN_KEY,
        );

        expect($attributes['business_id'])->toBeInt()->toBe(SUBSCRIPTION_MAPPER_BUSINESS_KEY)
            ->and($attributes['plan_id'])->toBeInt()->toBe(SUBSCRIPTION_MAPPER_PLAN_KEY);
    });

    it('writes empty columns for a subscription whose checkout never completed', function () {
        $attributes = (new SubscriptionMapper)->toAttributes(
            SubscriptionFixtures::opened(),
            SUBSCRIPTION_MAPPER_BUSINESS_KEY,
            SUBSCRIPTION_MAPPER_PLAN_KEY,
        );

        expect($attributes['status'])->toBe('incomplete')
            ->and($attributes['stripe_subscription_id'])->toBeNull()
            ->and($attributes['started_at'])->toBeNull()
            ->and($attributes['current_period_ends_at'])->toBeNull()
            ->and($attributes['canceled_at'])->toBeNull()
            ->and($attributes['payment_failed_at'])->toBeNull();
    });

    it('stores the instants in UTC through the model cast', function () {
        $model = new SubscriptionModel;

        $model->fill((new SubscriptionMapper)->toAttributes(
            SubscriptionFixtures::subscription(startedAt: '2026-06-01T17:00:00+02:00'),
            SUBSCRIPTION_MAPPER_BUSINESS_KEY,
            SUBSCRIPTION_MAPPER_PLAN_KEY,
        ));

        expect($model->getAttributes()['started_at'])->toBe('2026-06-01T15:00:00+00:00')
            ->and($model->getAttributes()['current_period_ends_at'])->toBe(SubscriptionFixtures::PERIOD_ENDS_AT);
    });
});

describe('a round trip', function () {
    it('brings back the subscription it wrote', function (Subscription $subscription) {
        $restored = roundTripped($subscription);

        expect($restored->id)->toBe($subscription->id)
            ->and($restored->businessId)->toBe($subscription->businessId)
            ->and($restored->billingCustomerId)->toBe($subscription->billingCustomerId)
            ->and($restored->planId())->toBe($subscription->planId())
            ->and($restored->plan())->toBe($subscription->plan())
            ->and($restored->status())->toBe($subscription->status())
            ->and($restored->billingSubscriptionId())->toBe($subscription->billingSubscriptionId())
            ->and($restored->startedAt())->toEqual($subscription->startedAt())
            ->and($restored->currentPeriodEndsAt())->toEqual($subscription->currentPeriodEndsAt())
            ->and($restored->canceledAt())->toEqual($subscription->canceledAt())
            ->and($restored->paymentFailedAt())->toEqual($subscription->paymentFailedAt())
            ->and($restored->createdAt)->toEqual($subscription->createdAt);
    })->with([
        'active' => fn () => SubscriptionFixtures::subscription(),
        'checkout never completed' => fn () => SubscriptionFixtures::opened(),
        'past due' => fn () => SubscriptionFixtures::subscription(
            status: SubscriptionStatus::PastDue,
            paymentFailedAt: '2026-06-14T09:30:00+00:00',
        ),
        'set to end with its period' => fn () => SubscriptionFixtures::subscription(canceledAt: '2026-06-10T15:00:00+00:00'),
        'canceled' => fn () => SubscriptionFixtures::subscription(
            status: SubscriptionStatus::Canceled,
            canceledAt: '2026-06-10T15:00:00+00:00',
        ),
        'renewing on the Madrid fall back night' => fn () => SubscriptionFixtures::subscription(currentPeriodEndsAt: '2026-10-25T00:30:00+00:00'),
        'renewing on the Madrid spring forward night' => fn () => SubscriptionFixtures::subscription(currentPeriodEndsAt: '2026-03-29T01:30:00+00:00'),
        'started at an offset instant' => fn () => SubscriptionFixtures::subscription(startedAt: '2026-06-01T09:00:00-06:00'),
    ]);
});
