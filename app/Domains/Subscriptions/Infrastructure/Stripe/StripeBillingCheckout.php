<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use App\Domains\Subscriptions\Contracts\BillingCheckout;
use App\Domains\Subscriptions\Exceptions\CheckoutSessionNotFound;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\CheckoutRequest;
use App\Domains\Subscriptions\ValueObjects\CheckoutSession;
use Stripe\Checkout\Session;
use Stripe\Subscription;

final class StripeBillingCheckout implements BillingCheckout
{
    private const SINGLE_SEAT = 1;

    public function __construct(
        private readonly StripeApi $stripe,
        private readonly StripeSnapshotMapper $snapshots,
    ) {}

    public function start(CheckoutRequest $request): CheckoutSession
    {
        $session = $this->stripe->createCheckoutSession([
            'mode' => Session::MODE_SUBSCRIPTION,
            'ui_mode' => Session::UI_MODE_ELEMENTS,
            'customer' => $request->billingCustomerId,
            'line_items' => [['price' => $request->billingPriceId, 'quantity' => self::SINGLE_SEAT]],
            'return_url' => $request->returnUrl,
            'metadata' => [StripeBillingCustomers::BUSINESS_METADATA_KEY => $request->businessId],
            'subscription_data' => self::subscriptionDataFor($request),
        ]);

        return new CheckoutSession((string) $session->id, (string) $session->client_secret);
    }

    public function subscriptionOf(string $sessionId, string $billingCustomerId): ?BillingSnapshot
    {
        $session = $this->sessionOf($sessionId);

        if (StripeSnapshotMapper::customerIdOf($session->customer ?? null) !== $billingCustomerId) {
            throw CheckoutSessionNotFound::withId($sessionId);
        }

        $subscription = $session->subscription ?? null;

        if ($subscription === null) {
            return null;
        }

        return $this->snapshots->toSnapshot($this->subscriptionFrom($subscription));
    }

    /**
     * @throws CheckoutSessionNotFound
     */
    private function sessionOf(string $sessionId): Session
    {
        try {
            return $this->stripe->retrieveCheckoutSession($sessionId);
        } catch (StripeApiFailure $failure) {
            if ($failure->concernsMissingResource()) {
                throw CheckoutSessionNotFound::withId($sessionId);
            }

            throw $failure;
        }
    }

    private function subscriptionFrom(Subscription|string $subscription): Subscription
    {
        if ($subscription instanceof Subscription) {
            return $subscription;
        }

        return $this->stripe->retrieveSubscription($subscription);
    }

    /**
     * @return array<string, mixed>
     */
    private static function subscriptionDataFor(CheckoutRequest $request): array
    {
        $subscriptionData = [
            'metadata' => [StripeBillingCustomers::BUSINESS_METADATA_KEY => $request->businessId],
        ];

        if ($request->trialDays === null) {
            return $subscriptionData;
        }

        return [...$subscriptionData, 'trial_period_days' => $request->trialDays];
    }
}
