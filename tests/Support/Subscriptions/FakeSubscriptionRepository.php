<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;
use Tests\Support\FakeTransactionManager;
use Throwable;

final class FakeSubscriptionRepository implements SubscriptionRepository
{
    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @var list<Subscription>|null */
    private ?array $reportedDue = null;

    private ?Throwable $saveFailure = null;

    /** @var list<Subscription> */
    public array $saved = [];

    /** @var list<bool> */
    public array $savedInsideTransaction = [];

    /** @var list<array{businessId: string, now: DateTimeImmutable}> */
    public array $inEffectLookups = [];

    /** @var list<array{businessIds: list<string>, now: DateTimeImmutable}> */
    public array $inEffectForManyLookups = [];

    /** @var list<DateTimeImmutable> */
    public array $dueLookups = [];

    public function __construct(
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function store(Subscription ...$subscriptions): self
    {
        foreach ($subscriptions as $subscription) {
            $this->subscriptions[$subscription->id] = $subscription;
        }

        return $this;
    }

    public function reportingDue(Subscription ...$subscriptions): self
    {
        $this->reportedDue = array_values($subscriptions);

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
        $this->savedInsideTransaction[] = $this->transactions?->isRunning() ?? false;
        $this->subscriptions[$subscription->id] = $subscription;
    }

    public function inEffectFor(string $businessId, DateTimeImmutable $now): ?Subscription
    {
        $this->inEffectLookups[] = ['businessId' => $businessId, 'now' => $now];

        return $this->findInEffect($businessId, $now);
    }

    public function inEffectForMany(array $businessIds, DateTimeImmutable $now): array
    {
        $this->inEffectForManyLookups[] = ['businessIds' => $businessIds, 'now' => $now];

        $inEffect = [];

        foreach ($businessIds as $businessId) {
            $subscription = $this->findInEffect($businessId, $now);

            if ($subscription !== null) {
                $inEffect[$businessId] = $subscription;
            }
        }

        return $inEffect;
    }

    public function dueForExpiry(DateTimeImmutable $now): array
    {
        $this->dueLookups[] = $now;

        return $this->reportedDue ?? array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription): bool => $subscription->status() === SubscriptionStatus::Active
                && $subscription->period()->hasEndedBy($now),
        ));
    }

    public function historyOf(string $businessId): array
    {
        return array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription): bool => $subscription->businessId === $businessId,
        ));
    }

    private function findInEffect(string $businessId, DateTimeImmutable $now): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->businessId === $businessId && $subscription->isInEffectAt($now)) {
                return $subscription;
            }
        }

        return null;
    }
}
