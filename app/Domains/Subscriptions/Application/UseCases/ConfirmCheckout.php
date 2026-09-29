<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\ConfirmCheckoutInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Contracts\BillingCheckout;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\CheckoutSessionNotFound;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Contracts\Events\Dispatcher;

final class ConfirmCheckout
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BillingCheckout $checkout,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<SubscriptionData>
     */
    public function handle(ConfirmCheckoutInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $subscription = $this->subscriptionFor($input);
            $transition = $this->confirm($subscription, $input->sessionId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($transition->eventsFor($subscription) as $event) {
            $this->events->dispatch($event);
        }

        return UseCaseResponse::success(SubscriptionData::fromSubscription($subscription, $this->clock->now()));
    }

    /**
     * @throws CheckoutSessionNotFound
     */
    private function subscriptionFor(ConfirmCheckoutInput $input): Subscription
    {
        return $this->subscriptions->forBusiness($input->businessId)
            ?? throw CheckoutSessionNotFound::withId($input->sessionId);
    }

    /**
     * @throws CheckoutSessionNotFound
     */
    private function confirm(Subscription $subscription, string $sessionId): SubscriptionTransition
    {
        $snapshot = $this->checkout->subscriptionOf($sessionId, $subscription->billingCustomerId);

        if ($snapshot === null) {
            return SubscriptionTransition::Unchanged;
        }

        $transition = $subscription->syncWith($snapshot, $this->clock->now());
        $this->subscriptions->save($subscription);

        return $transition;
    }
}
