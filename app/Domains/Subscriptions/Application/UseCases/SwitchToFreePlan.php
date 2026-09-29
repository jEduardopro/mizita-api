<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\Dtos\SwitchToFreePlanInput;
use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\SubscriptionAlreadyEnding;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotFound;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Contracts\Events\Dispatcher;

final class SwitchToFreePlan
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BillingSubscriptions $billing,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<SubscriptionData>
     */
    public function handle(SwitchToFreePlanInput $input): UseCaseResponse
    {
        try {
            $subscription = $this->subscriptions->forBusiness($input->businessId)
                ?? throw SubscriptionNotFound::forBusiness($input->businessId);

            $transition = $this->cancelAtPeriodEnd($subscription);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($transition->eventsFor($subscription) as $event) {
            $this->events->dispatch($event);
        }

        return UseCaseResponse::success(SubscriptionData::fromSubscription($subscription, $this->clock->now()));
    }

    /**
     * @throws SubscriptionNotActive
     * @throws SubscriptionAlreadyEnding
     */
    private function cancelAtPeriodEnd(Subscription $subscription): SubscriptionTransition
    {
        $subscription->ensureSwitchableToFree($this->clock->now());

        $snapshot = $this->billing->cancelAtPeriodEnd($subscription->requireBillingSubscriptionId());

        $transition = $subscription->syncWith($snapshot, $this->clock->now());
        $this->subscriptions->save($subscription);

        return $transition;
    }
}
