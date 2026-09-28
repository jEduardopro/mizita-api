<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;
use Illuminate\Contracts\Events\Dispatcher;

final class ExpireSubscriptions
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<int>
     */
    public function handle(): UseCaseResponse
    {
        try {
            $expired = $this->transactions->run(fn (): array => $this->expireDue());
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($expired as $subscription) {
            $this->events->dispatch(new SubscriptionEnded($subscription->id, $subscription->businessId));
        }

        return UseCaseResponse::success(count($expired));
    }

    /**
     * @return list<Subscription>
     */
    private function expireDue(): array
    {
        $now = $this->clock->now();
        $due = $this->subscriptions->dueForExpiry($now);

        foreach ($due as $subscription) {
            $subscription->expire($now);
            $this->subscriptions->save($subscription);
        }

        return $due;
    }
}
