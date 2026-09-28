<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\SubscriptionPeriodOverlaps;
use DateTimeImmutable;

interface SubscriptionRepository
{
    /**
     * @throws SubscriptionPeriodOverlaps
     */
    public function save(Subscription $subscription): void;

    public function inEffectFor(string $businessId, DateTimeImmutable $now): ?Subscription;

    /**
     * @param  list<string>  $businessIds
     * @return array<string, Subscription>
     */
    public function inEffectForMany(array $businessIds, DateTimeImmutable $now): array;

    /**
     * @return list<Subscription>
     */
    public function dueForExpiry(DateTimeImmutable $now): array;

    /**
     * @return list<Subscription>
     */
    public function historyOf(string $businessId): array;
}
