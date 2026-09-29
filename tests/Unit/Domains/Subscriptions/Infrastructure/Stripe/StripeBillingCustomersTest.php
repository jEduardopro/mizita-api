<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApi;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingCustomers;
use Stripe\ApiRequestor;
use Tests\Support\Subscriptions\FakeStripeHttpClient;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->http = (new FakeStripeHttpClient)->answering('POST', '/v1/customers', [
        'id' => SubscriptionFixtures::CREATED_BILLING_CUSTOMER_ID,
        'object' => 'customer',
    ]);
    ApiRequestor::setHttpClient($this->http);

    $this->customers = new StripeBillingCustomers(new StripeApi('sk_test_fake', 0));
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('hands back the id of the customer it created', function () {
    expect($this->customers->create(SubscriptionFixtures::contact()))->toBe(SubscriptionFixtures::CREATED_BILLING_CUSTOMER_ID);
});

it('creates the customer under the business name and owner email, tagged with the business uuid', function () {
    $this->customers->create(SubscriptionFixtures::contact());

    expect($this->http->requestedRoutes())->toBe(['POST /v1/customers'])
        ->and($this->http->requests[0]['params'])->toBe([
            'name' => SubscriptionFixtures::BUSINESS_NAME,
            'email' => SubscriptionFixtures::OWNER_EMAIL,
            'metadata' => ['business_id' => SubscriptionFixtures::BUSINESS_ID],
        ]);
});
