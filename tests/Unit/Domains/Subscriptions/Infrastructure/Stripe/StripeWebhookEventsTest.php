<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Stripe\StripeWebhookEvents;
use Stripe\Event;
use Tests\Support\Subscriptions\SubscriptionFixtures;

/**
 * @param  array<string, mixed>  $object
 */
function stripeEvent(string $type, array $object): Event
{
    return Event::constructFrom([
        'id' => 'evt_Test0000000000000001',
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $object],
    ]);
}

function subscriptionIdOf(Event $event): ?string
{
    return (new StripeWebhookEvents)->subscriptionIdOf($event);
}

describe('a completed checkout', function () {
    it('points at the subscription it created', function () {
        expect(subscriptionIdOf(stripeEvent(Event::CHECKOUT_SESSION_COMPLETED, [
            'id' => 'cs_test_Session0000000000000001',
            'object' => 'checkout.session',
            'subscription' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
        ])))->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);
    });

    it('points at the subscription it created when that arrives expanded', function () {
        expect(subscriptionIdOf(stripeEvent(Event::CHECKOUT_SESSION_COMPLETED, [
            'id' => 'cs_test_Session0000000000000001',
            'object' => 'checkout.session',
            'subscription' => ['id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID, 'object' => 'subscription'],
        ])))->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);
    });

    it('points at nothing when the session created no subscription', function () {
        expect(subscriptionIdOf(stripeEvent(Event::CHECKOUT_SESSION_COMPLETED, [
            'id' => 'cs_test_Session0000000000000001',
            'object' => 'checkout.session',
            'subscription' => null,
        ])))->toBeNull();
    });

    it('never mistakes the session id for a subscription id', function () {
        expect(subscriptionIdOf(stripeEvent(Event::CHECKOUT_SESSION_COMPLETED, [
            'id' => 'cs_test_Session0000000000000001',
            'object' => 'checkout.session',
        ])))->toBeNull();
    });
});

describe('a subscription lifecycle event', function () {
    it('points at the subscription it carries', function (string $type) {
        expect(subscriptionIdOf(stripeEvent($type, [
            'id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            'object' => 'subscription',
            'customer' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
        ])))->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);
    })->with([
        'created' => Event::CUSTOMER_SUBSCRIPTION_CREATED,
        'updated' => Event::CUSTOMER_SUBSCRIPTION_UPDATED,
        'deleted' => Event::CUSTOMER_SUBSCRIPTION_DELETED,
    ]);
});

describe('an invoice event', function () {
    it('points at the subscription the invoice bills', function (string $type) {
        expect(subscriptionIdOf(stripeEvent($type, [
            'id' => 'in_Test0000000000000001',
            'object' => 'invoice',
            'customer' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
            'parent' => [
                'type' => 'subscription_details',
                'subscription_details' => ['subscription' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
            ],
        ])))->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);
    })->with([
        'paid' => Event::INVOICE_PAID,
        'payment failed' => Event::INVOICE_PAYMENT_FAILED,
    ]);

    it('points at nothing for an invoice that bills no subscription', function () {
        expect(subscriptionIdOf(stripeEvent(Event::INVOICE_PAID, [
            'id' => 'in_Test0000000000000001',
            'object' => 'invoice',
            'parent' => null,
        ])))->toBeNull();
    });

    it('never mistakes the invoice id for a subscription id', function () {
        expect(subscriptionIdOf(stripeEvent(Event::INVOICE_PAYMENT_FAILED, [
            'id' => 'in_Test0000000000000001',
            'object' => 'invoice',
        ])))->toBeNull();
    });

    it('points at nothing when the subscription reference is empty', function () {
        expect(subscriptionIdOf(stripeEvent(Event::INVOICE_PAID, [
            'id' => 'in_Test0000000000000001',
            'object' => 'invoice',
            'parent' => ['subscription_details' => ['subscription' => '']],
        ])))->toBeNull();
    });
});

describe('any other event', function () {
    it('points at nothing, even when its object looks like a subscription', function (string $type) {
        expect(subscriptionIdOf(stripeEvent($type, [
            'id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            'object' => 'subscription',
            'subscription' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
        ])))->toBeNull();
    })->with([
        'customer created' => Event::CUSTOMER_CREATED,
        'invoice created' => Event::INVOICE_CREATED,
        'trial ending soon' => Event::CUSTOMER_SUBSCRIPTION_TRIAL_WILL_END,
        'checkout expired' => Event::CHECKOUT_SESSION_EXPIRED,
        'payment succeeded' => Event::PAYMENT_INTENT_SUCCEEDED,
    ]);
});
