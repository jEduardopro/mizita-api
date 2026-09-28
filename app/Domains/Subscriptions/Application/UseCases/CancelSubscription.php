<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\CancelSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Contracts\BusinessDirectory;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Contracts\Events\Dispatcher;

final class CancelSubscription
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BusinessDirectory $businesses,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<SubscriptionData>
     */
    public function handle(CancelSubscriptionInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $subscription = $this->cancel($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new SubscriptionEnded($subscription->id, $subscription->businessId));

        return UseCaseResponse::success(SubscriptionData::fromEntity($subscription));
    }

    private function cancel(CancelSubscriptionInput $input): Subscription
    {
        $businessId = $this->businesses->idForSlug($input->businessSlug()->value);
        $now = $this->clock->now();

        $subscription = $this->subscriptions->inEffectFor($businessId, $now)
            ?? throw SubscriptionNotFound::inEffectFor($businessId);

        $subscription->cancel($now);

        $this->subscriptions->save($subscription);

        return $subscription;
    }
}
