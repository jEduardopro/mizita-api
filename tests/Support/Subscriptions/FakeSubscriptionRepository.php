<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use DateTimeImmutable;
use Throwable;

final class FakeSubscriptionRepository implements SubscriptionRepository
{
    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @var list<Subscription>|null */
    private ?array $reportedPastPaymentGrace = null;

    /** @var list<Subscription>|null */
    private ?array $reportedLapsed = null;

    private ?Throwable $saveFailure = null;

    /** @var list<Subscription> */
    public array $saved = [];

    /** @var list<string> */
    public array $businessLookups = [];

    /** @var list<string> */
    public array $billingCustomerLookups = [];

    /** @var list<list<string>> */
    public array $manyBusinessesLookups = [];

    /** @var list<DateTimeImmutable> */
    public array $pastPaymentGraceLookups = [];

    /** @var list<DateTimeImmutable> */
    public array $lapsedLookups = [];

    public function __construct(
        private readonly ?SubscriptionJournal $journal = null,
    ) {}

    public function store(Subscription ...$subscriptions): self
    {
        foreach ($subscriptions as $subscription) {
            $this->subscriptions[$subscription->id] = $subscription;
        }

        return $this;
    }

    public function reportingPastPaymentGrace(Subscription ...$subscriptions): self
    {
        $this->reportedPastPaymentGrace = array_values($subscriptions);

        return $this;
    }

    public function reportingLapsed(Subscription ...$subscriptions): self
    {
        $this->reportedLapsed = array_values($subscriptions);

        return $this;
    }

    public function failingOnSave(Throwable $failure): self
    {
        $this->saveFailure = $failure;

        return $this;
    }

    public function save(Subscription $subscription): void
    {
        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }

        $this->saved[] = $subscription;
        $this->subscriptions[$subscription->id] = $subscription;
        $this->journal?->record('saved '.$subscription->id);
    }

    public function forBusiness(string $businessId): ?Subscription
    {
        $this->businessLookups[] = $businessId;

        foreach ($this->subscriptions as $subscription) {
            if ($subscription->businessId === $businessId) {
                return $subscription;
            }
        }

        return null;
    }

    public function forBillingCustomer(string $billingCustomerId): ?Subscription
    {
        $this->billingCustomerLookups[] = $billingCustomerId;

        foreach ($this->subscriptions as $subscription) {
            if ($subscription->billingCustomerId === $billingCustomerId) {
                return $subscription;
            }
        }

        return null;
    }

    public function forManyBusinesses(array $businessIds): array
    {
        $this->manyBusinessesLookups[] = $businessIds;

        $found = [];

        foreach ($this->subscriptions as $subscription) {
            if (in_array($subscription->businessId, $businessIds, true)) {
                $found[$subscription->businessId] = $subscription;
            }
        }

        return $found;
    }

    public function pastPaymentGrace(DateTimeImmutable $now): array
    {
        $this->pastPaymentGraceLookups[] = $now;

        return $this->reportedPastPaymentGrace ?? array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription): bool => $subscription->isPastPaymentGraceAt($now),
        ));
    }

    public function lapsedWithoutEnding(DateTimeImmutable $now): array
    {
        $this->lapsedLookups[] = $now;

        return $this->reportedLapsed ?? array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription): bool => $subscription->status()->entitles()
                && ! $subscription->grantsAccessAt($now),
        ));
    }
}
