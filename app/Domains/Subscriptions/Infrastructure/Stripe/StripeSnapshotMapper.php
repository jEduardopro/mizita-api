<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\SubscriptionItem;

final class StripeSnapshotMapper
{
    private const UNIX_TIMESTAMP_PREFIX = '@';

    public function toSnapshot(Subscription $subscription): BillingSnapshot
    {
        return new BillingSnapshot(
            subscriptionId: $subscription->id,
            billingCustomerId: self::customerIdOf($subscription->customer),
            status: SubscriptionStatus::from($subscription->status),
            startedAt: self::instantOf($subscription->start_date ?? null),
            currentPeriodEndsAt: self::instantOf(self::firstItemOf($subscription)?->current_period_end),
            canceledAt: self::instantOf($subscription->canceled_at ?? null),
            cancelAtPeriodEnd: (bool) ($subscription->cancel_at_period_end ?? false)
                || ($subscription->cancel_at ?? null) !== null,
        );
    }

    public static function customerIdOf(Customer|string|null $customer): string
    {
        return $customer instanceof Customer ? $customer->id : (string) $customer;
    }

    private static function firstItemOf(Subscription $subscription): ?SubscriptionItem
    {
        $items = $subscription->items->data ?? [];

        return $items[0] ?? null;
    }

    private static function instantOf(?int $timestamp): ?DateTimeImmutable
    {
        if ($timestamp === null) {
            return null;
        }

        return new DateTimeImmutable(self::UNIX_TIMESTAMP_PREFIX.$timestamp);
    }
}
