<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\CheckoutAlreadyStarted;
use DateTimeImmutable;

interface SubscriptionRepository
{
    /**
     * @throws CheckoutAlreadyStarted
     */
    public function save(Subscription $subscription): void;

    public function forBusiness(string $businessId): ?Subscription;

    public function forBillingCustomer(string $billingCustomerId): ?Subscription;

    /**
     * @param  list<string>  $businessIds
     * @return array<string, Subscription>
     */
    public function forManyBusinesses(array $businessIds): array;

    /**
     * @return iterable<string>
     */
    public function businessIdsPastPaymentGrace(DateTimeImmutable $now): iterable;

    /**
     * @return list<Subscription>
     */
    public function lapsedWithoutEnding(DateTimeImmutable $now): array;
}
