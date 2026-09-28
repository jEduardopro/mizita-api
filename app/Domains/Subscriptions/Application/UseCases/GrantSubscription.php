<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\GrantSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Contracts\BusinessDirectory;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionStarted;
use App\Domains\Subscriptions\Exceptions\SubscriptionAlreadyInEffect;
use App\Domains\Subscriptions\ValueObjects\LastIncludedDay;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;

final class GrantSubscription
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BusinessDirectory $businesses,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<SubscriptionData>
     */
    public function handle(GrantSubscriptionInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $subscription = $this->grant($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new SubscriptionStarted($subscription->id, $subscription->businessId));

        return UseCaseResponse::success(SubscriptionData::fromEntity($subscription));
    }

    private function grant(GrantSubscriptionInput $input): Subscription
    {
        $businessId = $this->businesses->idForSlug($input->businessSlug()->value);
        $now = $this->clock->now();

        $this->assertNoneInEffect($businessId, $now);

        $plan = $input->plan();

        $subscription = Subscription::grant(
            id: $this->ids->next(),
            businessId: $businessId,
            plan: $plan,
            period: SubscriptionPeriod::between($now, $this->exclusiveEndOf($input->lastIncludedDay(), $businessId)),
            price: $this->priceFor($plan, $input->amountInMinorUnits()),
            now: $now,
        );

        $this->subscriptions->save($subscription);

        return $subscription;
    }

    /**
     * @throws SubscriptionAlreadyInEffect
     */
    private function assertNoneInEffect(string $businessId, DateTimeImmutable $now): void
    {
        if ($this->subscriptions->inEffectFor($businessId, $now) !== null) {
            throw SubscriptionAlreadyInEffect::forBusiness($businessId);
        }
    }

    private function exclusiveEndOf(LastIncludedDay $lastIncludedDay, string $businessId): DateTimeImmutable
    {
        return $lastIncludedDay->exclusiveEndIn(new DateTimeZone($this->businesses->timezoneOf($businessId)));
    }

    private function priceFor(Plan $plan, ?int $amountInMinorUnits): SubscriptionPrice
    {
        if ($amountInMinorUnits === null) {
            return $plan->listPrice();
        }

        return $plan->listPrice()->withAmount($amountInMinorUnits);
    }
}
