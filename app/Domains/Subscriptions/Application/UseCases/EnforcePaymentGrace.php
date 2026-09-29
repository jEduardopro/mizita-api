<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use Illuminate\Contracts\Events\Dispatcher;

final class EnforcePaymentGrace
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BillingSubscriptions $billing,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<int>
     */
    public function handle(): UseCaseResponse
    {
        $now = $this->clock->now();
        $canceled = 0;

        foreach ($this->subscriptions->pastPaymentGrace($now) as $subscription) {
            $billingSubscriptionId = $subscription->billingSubscriptionId();

            if ($billingSubscriptionId === null || ! $subscription->isPastPaymentGraceAt($now)) {
                continue;
            }

            $this->cancel($subscription, $billingSubscriptionId);
            $canceled++;
        }

        return UseCaseResponse::success($canceled);
    }

    private function cancel(Subscription $subscription, string $billingSubscriptionId): void
    {
        $snapshot = $this->billing->cancelNow($billingSubscriptionId);

        $transition = $subscription->syncWith($snapshot, $this->clock->now());
        $this->subscriptions->save($subscription);

        foreach ($transition->eventsFor($subscription) as $event) {
            $this->events->dispatch($event);
        }
    }
}
