<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Entities;

use App\Domains\Subscriptions\Exceptions\SubscriptionAlreadyActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionAlreadyEnding;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotResumable;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use DateInterval;
use DateTimeImmutable;

final class Subscription
{
    public const RENEWAL_LEEWAY = 'P1D';

    public const PAYMENT_GRACE = 'P5D';

    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $billingCustomerId,
        private string $planId,
        private Plan $plan,
        private SubscriptionStatus $status,
        private ?string $billingSubscriptionId,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $currentPeriodEndsAt,
        private ?DateTimeImmutable $canceledAt,
        private ?DateTimeImmutable $paymentFailedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function open(
        string $id,
        string $businessId,
        PlanOffer $offer,
        string $billingCustomerId,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            billingCustomerId: $billingCustomerId,
            planId: $offer->id,
            plan: $offer->key,
            status: SubscriptionStatus::Incomplete,
            billingSubscriptionId: null,
            startedAt: null,
            currentPeriodEndsAt: null,
            canceledAt: null,
            paymentFailedAt: null,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $billingCustomerId,
        string $planId,
        Plan $plan,
        SubscriptionStatus $status,
        ?string $billingSubscriptionId,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $currentPeriodEndsAt,
        ?DateTimeImmutable $canceledAt,
        ?DateTimeImmutable $paymentFailedAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            billingCustomerId: $billingCustomerId,
            planId: $planId,
            plan: $plan,
            status: $status,
            billingSubscriptionId: $billingSubscriptionId,
            startedAt: $startedAt,
            currentPeriodEndsAt: $currentPeriodEndsAt,
            canceledAt: $canceledAt,
            paymentFailedAt: $paymentFailedAt,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws SubscriptionAlreadyActive
     */
    public function beginCheckout(PlanOffer $offer, DateTimeImmutable $now): void
    {
        if (! $this->canCheckout($now)) {
            throw SubscriptionAlreadyActive::forBusiness($this->businessId);
        }

        $this->planId = $offer->id;
        $this->plan = $offer->key;
    }

    public function trialDaysFor(PlanOffer $offer): ?int
    {
        if ($this->startedAt !== null) {
            return null;
        }

        return $offer->trialDays;
    }

    public function syncWith(BillingSnapshot $snapshot, DateTimeImmutable $now): SubscriptionTransition
    {
        if ($this->isSupersededBy($snapshot)) {
            return SubscriptionTransition::Unchanged;
        }

        $entitledBefore = $this->status->entitles();

        $this->paymentFailedAt = $this->paymentFailureAfter($snapshot->status, $now);
        $this->status = $snapshot->status;
        $this->billingSubscriptionId = $snapshot->subscriptionId;
        $this->startedAt = $this->startAfter($snapshot);
        $this->currentPeriodEndsAt = $snapshot->currentPeriodEndsAt;
        $this->canceledAt = $snapshot->requestsCancellation() ? ($snapshot->canceledAt ?? $now) : null;

        return SubscriptionTransition::between($entitledBefore, $this->status->entitles());
    }

    public function grantsAccessAt(DateTimeImmutable $now): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Active, SubscriptionStatus::Trialing => $this->isWithinPaidPeriodAt($now),
            SubscriptionStatus::PastDue => $this->isWithinPaymentGraceAt($now),
            default => false,
        };
    }

    public function planGrantedAt(DateTimeImmutable $now): Plan
    {
        return $this->grantsAccessAt($now) ? $this->plan : Plan::Free;
    }

    public function isPastPaymentGraceAt(DateTimeImmutable $now): bool
    {
        $graceEndsAt = $this->paymentGraceEndsAt();

        return $this->status === SubscriptionStatus::PastDue
            && $graceEndsAt !== null
            && $now >= $graceEndsAt;
    }

    public function canCheckout(DateTimeImmutable $now): bool
    {
        return ! $this->grantsAccessAt($now);
    }

    public function canResume(DateTimeImmutable $now): bool
    {
        return $this->canceledAt !== null && $this->grantsAccessAt($now);
    }

    public function canSwitchToFree(DateTimeImmutable $now): bool
    {
        return $this->canceledAt === null && $this->grantsAccessAt($now);
    }

    /**
     * @throws SubscriptionNotActive
     * @throws SubscriptionAlreadyEnding
     */
    public function ensureSwitchableToFree(DateTimeImmutable $now): void
    {
        if (! $this->grantsAccessAt($now)) {
            throw SubscriptionNotActive::withId($this->id);
        }

        if ($this->canceledAt !== null) {
            throw SubscriptionAlreadyEnding::withId($this->id);
        }
    }

    /**
     * @throws SubscriptionNotResumable
     */
    public function ensureResumable(DateTimeImmutable $now): void
    {
        if (! $this->canResume($now)) {
            throw SubscriptionNotResumable::withId($this->id);
        }
    }

    /**
     * @throws SubscriptionNotActive
     */
    public function requireBillingSubscriptionId(): string
    {
        if ($this->billingSubscriptionId === null) {
            throw SubscriptionNotActive::withId($this->id);
        }

        return $this->billingSubscriptionId;
    }

    public function paymentGraceEndsAt(): ?DateTimeImmutable
    {
        return $this->paymentFailedAt?->add(new DateInterval(self::PAYMENT_GRACE));
    }

    public function planId(): string
    {
        return $this->planId;
    }

    public function plan(): Plan
    {
        return $this->plan;
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function billingSubscriptionId(): ?string
    {
        return $this->billingSubscriptionId;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function currentPeriodEndsAt(): ?DateTimeImmutable
    {
        return $this->currentPeriodEndsAt;
    }

    public function canceledAt(): ?DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function paymentFailedAt(): ?DateTimeImmutable
    {
        return $this->paymentFailedAt;
    }

    private function isSupersededBy(BillingSnapshot $snapshot): bool
    {
        return $this->billingSubscriptionId !== null
            && $snapshot->subscriptionId !== $this->billingSubscriptionId
            && $snapshot->status->isTerminal();
    }

    private function paymentFailureAfter(SubscriptionStatus $status, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if ($status === SubscriptionStatus::PastDue) {
            return $this->paymentFailedAt ?? $now;
        }

        if ($status->isPaidUp()) {
            return null;
        }

        return $this->paymentFailedAt;
    }

    private function startAfter(BillingSnapshot $snapshot): ?DateTimeImmutable
    {
        if ($this->startedAt !== null || ! $snapshot->status->isPaidUp()) {
            return $this->startedAt;
        }

        return $snapshot->startedAt;
    }

    private function isWithinPaidPeriodAt(DateTimeImmutable $now): bool
    {
        if ($this->currentPeriodEndsAt === null) {
            return false;
        }

        $accessEndsAt = $this->canceledAt === null
            ? $this->currentPeriodEndsAt->add(new DateInterval(self::RENEWAL_LEEWAY))
            : $this->currentPeriodEndsAt;

        return $now < $accessEndsAt;
    }

    private function isWithinPaymentGraceAt(DateTimeImmutable $now): bool
    {
        $graceEndsAt = $this->paymentGraceEndsAt();

        return $graceEndsAt !== null && $now < $graceEndsAt;
    }
}
