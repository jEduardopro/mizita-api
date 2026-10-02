<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\CancelSubscriptionPastPaymentGraceInput;
use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use Illuminate\Contracts\Events\Dispatcher;

final class CancelSubscriptionPastPaymentGrace
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BillingSubscriptions $billing,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<SubscriptionTransition>
     */
    public function handle(CancelSubscriptionPastPaymentGraceInput $input): UseCaseResponse
    {
        $subscription = $this->subscriptions->forBusiness($input->businessId);
        $billingSubscriptionId = $subscription?->billingSubscriptionId();

        if ($subscription === null
            || $billingSubscriptionId === null
            || ! $subscription->isPastPaymentGraceAt($this->clock->now())) {
            return UseCaseResponse::success(SubscriptionTransition::Unchanged);
        }

        $transition = $this->cancel($subscription, $billingSubscriptionId);

        foreach ($transition->eventsFor($subscription) as $event) {
            $this->events->dispatch($event);
        }

        return UseCaseResponse::success($transition);
    }

    private function cancel(Subscription $subscription, string $billingSubscriptionId): SubscriptionTransition
    {
        $snapshot = $this->billing->cancelNow($billingSubscriptionId);

        $transition = $subscription->syncWith($snapshot, $this->clock->now());
        $this->subscriptions->save($subscription);

        return $transition;
    }
}
