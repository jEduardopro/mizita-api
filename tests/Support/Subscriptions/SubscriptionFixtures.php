<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\BillingContact;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;

final class SubscriptionFixtures
{
    public const BUSINESS_ID = '01930000-0000-7000-8000-00000000b001';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-00000000b002';

    public const SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000c001';

    public const OTHER_SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000c002';

    public const GENERATED_SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000c0ff';

    public const PLAN_ID = '01930000-0000-7000-8000-00000000d001';

    public const OTHER_PLAN_ID = '01930000-0000-7000-8000-00000000d002';

    public const BILLING_CUSTOMER_ID = 'cus_Test0000000000000001';

    public const OTHER_BILLING_CUSTOMER_ID = 'cus_Test0000000000000002';

    public const CREATED_BILLING_CUSTOMER_ID = 'cus_TestCreated000000001';

    public const BILLING_SUBSCRIPTION_ID = 'sub_Test0000000000000001';

    public const OTHER_BILLING_SUBSCRIPTION_ID = 'sub_Test0000000000000002';

    public const BILLING_PRICE_ID = 'price_Test000000000000001';

    public const OTHER_BILLING_PRICE_ID = 'price_Test000000000000002';

    public const PLAN_NAME = 'Completo';

    public const COMPLETE_PRICE = 20000;

    public const TRIAL_DAYS = 14;

    public const BUSINESS_NAME = 'Barbería Centro';

    public const OWNER_EMAIL = 'owner@barberia-centro.test';

    public const NOW = '2026-06-15T15:00:00+00:00';

    public const CREATED_AT = '2026-06-01T14:50:00+00:00';

    public const STARTED_AT = '2026-06-01T15:00:00+00:00';

    public const PERIOD_ENDS_AT = '2026-07-01T15:00:00+00:00';

    public const RENEWED_PERIOD_ENDS_AT = '2026-08-01T15:00:00+00:00';

    public static function instant(string $atom): DateTimeImmutable
    {
        return new DateTimeImmutable($atom);
    }

    public static function now(): DateTimeImmutable
    {
        return self::instant(self::NOW);
    }

    public static function offer(
        ?int $trialDays = null,
        string $id = self::PLAN_ID,
        string $billingPriceId = self::BILLING_PRICE_ID,
        Plan $key = Plan::Complete,
        string $name = self::PLAN_NAME,
        int $amount = self::COMPLETE_PRICE,
    ): PlanOffer {
        return new PlanOffer(
            id: $id,
            key: $key,
            name: $name,
            price: SubscriptionPrice::of($amount, CurrencyCode::default()),
            interval: BillingInterval::Month,
            trialDays: $trialDays,
            billingPriceId: $billingPriceId,
        );
    }

    public static function subscription(
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?string $currentPeriodEndsAt = self::PERIOD_ENDS_AT,
        ?string $startedAt = self::STARTED_AT,
        ?string $canceledAt = null,
        ?string $paymentFailedAt = null,
        ?string $billingSubscriptionId = self::BILLING_SUBSCRIPTION_ID,
        string $id = self::SUBSCRIPTION_ID,
        string $businessId = self::BUSINESS_ID,
        string $billingCustomerId = self::BILLING_CUSTOMER_ID,
        Plan $plan = Plan::Complete,
        string $planId = self::PLAN_ID,
    ): Subscription {
        return Subscription::restore(
            id: $id,
            businessId: $businessId,
            billingCustomerId: $billingCustomerId,
            planId: $planId,
            plan: $plan,
            status: $status,
            billingSubscriptionId: $billingSubscriptionId,
            startedAt: self::optionalInstant($startedAt),
            currentPeriodEndsAt: self::optionalInstant($currentPeriodEndsAt),
            canceledAt: self::optionalInstant($canceledAt),
            paymentFailedAt: self::optionalInstant($paymentFailedAt),
            createdAt: self::instant(self::CREATED_AT),
        );
    }

    public static function opened(
        string $id = self::SUBSCRIPTION_ID,
        string $businessId = self::BUSINESS_ID,
        string $billingCustomerId = self::BILLING_CUSTOMER_ID,
        ?PlanOffer $offer = null,
    ): Subscription {
        return Subscription::open(
            id: $id,
            businessId: $businessId,
            offer: $offer ?? self::offer(),
            billingCustomerId: $billingCustomerId,
            now: self::instant(self::CREATED_AT),
        );
    }

    public static function snapshot(
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?string $currentPeriodEndsAt = self::PERIOD_ENDS_AT,
        ?string $startedAt = self::STARTED_AT,
        ?string $canceledAt = null,
        bool $cancelAtPeriodEnd = false,
        string $subscriptionId = self::BILLING_SUBSCRIPTION_ID,
        string $billingCustomerId = self::BILLING_CUSTOMER_ID,
    ): BillingSnapshot {
        return new BillingSnapshot(
            subscriptionId: $subscriptionId,
            billingCustomerId: $billingCustomerId,
            status: $status,
            startedAt: self::optionalInstant($startedAt),
            currentPeriodEndsAt: self::optionalInstant($currentPeriodEndsAt),
            canceledAt: self::optionalInstant($canceledAt),
            cancelAtPeriodEnd: $cancelAtPeriodEnd,
        );
    }

    public static function contact(string $businessId = self::BUSINESS_ID): BillingContact
    {
        return new BillingContact(
            businessId: $businessId,
            name: self::BUSINESS_NAME,
            email: self::OWNER_EMAIL,
        );
    }

    private static function optionalInstant(?string $atom): ?DateTimeImmutable
    {
        return $atom === null ? null : self::instant($atom);
    }
}
