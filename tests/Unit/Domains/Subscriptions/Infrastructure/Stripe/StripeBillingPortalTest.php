<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApi;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingPortal;
use Stripe\ApiRequestor;
use Tests\Support\Subscriptions\FakeStripeHttpClient;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const STRIPE_PORTAL_URL = 'https://billing.stripe.com/p/session/test_Portal000001';

const STRIPE_PORTAL_RETURN_URL = 'https://mizita.test/settings/plan';

beforeEach(function () {
    $this->http = (new FakeStripeHttpClient)->answering('POST', '/v1/billing_portal/sessions', [
        'id' => 'bps_Test0000000000000001',
        'object' => 'billing_portal.session',
        'url' => STRIPE_PORTAL_URL,
    ]);
    ApiRequestor::setHttpClient($this->http);

    $this->portal = new StripeBillingPortal(new StripeApi('sk_test_fake', 0));
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('hands back the url of the portal session it opened', function () {
    expect($this->portal->urlFor(SubscriptionFixtures::BILLING_CUSTOMER_ID, STRIPE_PORTAL_RETURN_URL))->toBe(STRIPE_PORTAL_URL);
});

it('opens the portal for that customer, returning to the given url', function () {
    $this->portal->urlFor(SubscriptionFixtures::BILLING_CUSTOMER_ID, STRIPE_PORTAL_RETURN_URL);

    expect($this->http->requestedRoutes())->toBe(['POST /v1/billing_portal/sessions'])
        ->and($this->http->requests[0]['params'])->toBe([
            'customer' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
            'return_url' => STRIPE_PORTAL_RETURN_URL,
        ]);
});
