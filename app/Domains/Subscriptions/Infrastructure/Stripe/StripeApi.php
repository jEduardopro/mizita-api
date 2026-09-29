<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use Closure;
use SensitiveParameter;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Subscription;

final class StripeApi
{
    private readonly StripeClient $client;

    public function __construct(
        #[SensitiveParameter] string $secretKey,
        int $maxNetworkRetries,
    ) {
        $this->client = new StripeClient([
            'api_key' => $secretKey === '' ? null : $secretKey,
            'max_network_retries' => $maxNetworkRetries,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function createCustomer(array $parameters): Customer
    {
        return $this->call('POST /v1/customers', fn (): Customer => $this->client->customers->create($parameters));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function createCheckoutSession(array $parameters): CheckoutSession
    {
        return $this->call(
            'POST /v1/checkout/sessions',
            fn (): CheckoutSession => $this->client->checkout->sessions->create($parameters),
        );
    }

    public function retrieveCheckoutSession(string $sessionId): CheckoutSession
    {
        return $this->call(
            'GET /v1/checkout/sessions/{id}',
            fn (): CheckoutSession => $this->client->checkout->sessions->retrieve($sessionId),
        );
    }

    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        return $this->call(
            'GET /v1/subscriptions/{id}',
            fn (): Subscription => $this->client->subscriptions->retrieve($subscriptionId),
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function updateSubscription(string $subscriptionId, array $parameters): Subscription
    {
        return $this->call(
            'POST /v1/subscriptions/{id}',
            fn (): Subscription => $this->client->subscriptions->update($subscriptionId, $parameters),
        );
    }

    public function cancelSubscription(string $subscriptionId): Subscription
    {
        return $this->call(
            'DELETE /v1/subscriptions/{id}',
            fn (): Subscription => $this->client->subscriptions->cancel($subscriptionId),
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function createPortalSession(array $parameters): PortalSession
    {
        return $this->call(
            'POST /v1/billing_portal/sessions',
            fn (): PortalSession => $this->client->billingPortal->sessions->create($parameters),
        );
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $request
     * @return TResult
     *
     * @throws StripeApiFailure
     */
    private function call(string $operation, Closure $request): mixed
    {
        try {
            return $request();
        } catch (ApiErrorException $failure) {
            throw StripeApiFailure::requestFailed($operation, $failure);
        }
    }
}
