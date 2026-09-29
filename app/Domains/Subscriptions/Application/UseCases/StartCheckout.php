<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\CheckoutSessionData;
use App\Domains\Subscriptions\Application\Dtos\StartCheckoutInput;
use App\Domains\Subscriptions\Contracts\BillingCheckout;
use App\Domains\Subscriptions\Contracts\BillingContacts;
use App\Domains\Subscriptions\Contracts\BillingCustomers;
use App\Domains\Subscriptions\Contracts\PlanCatalog;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\CheckoutAlreadyStarted;
use App\Domains\Subscriptions\Exceptions\SubscriptionAlreadyActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;
use App\Domains\Subscriptions\ValueObjects\CheckoutRequest;
use App\Domains\Subscriptions\ValueObjects\CheckoutSession;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class StartCheckout
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanCatalog $plans,
        private readonly BillingContacts $contacts,
        private readonly BillingCustomers $customers,
        private readonly BillingCheckout $checkout,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly string $returnUrl,
    ) {}

    /**
     * @return UseCaseResponse<CheckoutSessionData>
     */
    public function handle(StartCheckoutInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $session = $this->start($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(CheckoutSessionData::fromSession($session));
    }

    private function start(StartCheckoutInput $input): CheckoutSession
    {
        $offer = $this->plans->findById($input->planId);
        $subscription = $this->prepare($input->businessId, $offer);

        return $this->checkout->start(new CheckoutRequest(
            businessId: $subscription->businessId,
            billingCustomerId: $subscription->billingCustomerId,
            billingPriceId: $offer->billingPriceId,
            trialDays: $subscription->trialDaysFor($offer),
            returnUrl: $this->returnUrl,
        ));
    }

    /**
     * @throws SubscriptionAlreadyActive
     * @throws SubscriptionBusinessNotFound
     * @throws CheckoutAlreadyStarted
     */
    private function prepare(string $businessId, PlanOffer $offer): Subscription
    {
        $now = $this->clock->now();
        $subscription = $this->subscriptions->forBusiness($businessId);

        if ($subscription === null) {
            $subscription = Subscription::open(
                id: $this->ids->next(),
                businessId: $businessId,
                offer: $offer,
                billingCustomerId: $this->customers->create($this->contacts->ownerOf($businessId)),
                now: $now,
            );
        }

        $subscription->beginCheckout($offer, $now);
        $this->subscriptions->save($subscription);

        return $subscription;
    }
}
