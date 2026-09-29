<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\ResumeSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotFound;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotResumable;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Contracts\Events\Dispatcher;

final class ResumeSubscription
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
    public function handle(ResumeSubscriptionInput $input): UseCaseResponse
    {
        try {
            $subscription = $this->subscriptions->forBusiness($input->businessId)
                ?? throw SubscriptionNotFound::forBusiness($input->businessId);

            $transition = $this->resume($subscription);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($transition->eventsFor($subscription) as $event) {
            $this->events->dispatch($event);
        }

        return UseCaseResponse::success(SubscriptionData::fromSubscription($subscription, $this->clock->now()));
    }

    /**
     * @throws SubscriptionNotResumable
     * @throws SubscriptionNotActive
     */
    private function resume(Subscription $subscription): SubscriptionTransition
    {
        $subscription->ensureResumable($this->clock->now());

        $snapshot = $this->billing->resume($subscription->requireBillingSubscriptionId());

        $transition = $subscription->syncWith($snapshot, $this->clock->now());
        $this->subscriptions->save($subscription);

        return $transition;
    }
}
