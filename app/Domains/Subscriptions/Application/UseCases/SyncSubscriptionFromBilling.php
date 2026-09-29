<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\SyncSubscriptionFromBillingInput;
use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use Illuminate\Contracts\Events\Dispatcher;

final class SyncSubscriptionFromBilling
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
    public function handle(SyncSubscriptionFromBillingInput $input): UseCaseResponse
    {
        $snapshot = $this->billing->fetch($input->billingSubscriptionId);
        $subscription = $this->subscriptions->forBillingCustomer($snapshot->billingCustomerId);

        if ($subscription === null) {
            return UseCaseResponse::success(SubscriptionTransition::Unchanged);
        }

        $transition = $subscription->syncWith($snapshot, $this->clock->now());
        $this->subscriptions->save($subscription);

        foreach ($transition->eventsFor($subscription) as $event) {
            $this->events->dispatch($event);
        }

        return UseCaseResponse::success($transition);
    }
}
