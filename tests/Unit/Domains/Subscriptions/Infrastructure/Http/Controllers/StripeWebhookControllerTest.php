<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Http\Controllers\StripeWebhookController;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeWebhookEvents;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\WebhookSignature;
use Tests\Support\Subscriptions\FakeSubscriptionSyncQueue;
use Tests\Support\Subscriptions\SubscriptionFixtures;
use Tests\TestCase;

uses(TestCase::class);

const STRIPE_WEBHOOK_SECRET = 'whsec_test_Secret000000000000000001';

const STRIPE_WEBHOOK_STALE_SECONDS = 600;

function stripeWebhookPayload(string $type = Event::CUSTOMER_SUBSCRIPTION_UPDATED): string
{
    return json_encode([
        'id' => 'evt_Test0000000000000001',
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => [
            'id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            'object' => 'subscription',
        ]],
    ], JSON_THROW_ON_ERROR);
}

function stripeWebhookRequest(string $payload, ?string $signature): Request
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($signature !== null) {
        $server['HTTP_STRIPE_SIGNATURE'] = $signature;
    }

    return Request::create('/api/webhooks/stripe', 'POST', [], [], [], $server, $payload);
}

function signedStripeWebhookRequest(string $payload, string $secret = STRIPE_WEBHOOK_SECRET, ?int $timestamp = null): Request
{
    return stripeWebhookRequest($payload, WebhookSignature::generateSignatureHeader($payload, $secret, $timestamp));
}

beforeEach(function () {
    $this->syncQueue = new FakeSubscriptionSyncQueue;
    $this->logger = Mockery::spy(LoggerInterface::class);

    $this->receive = fn (Request $request, ?FakeSubscriptionSyncQueue $syncQueue = null): JsonResponse => (new StripeWebhookController(STRIPE_WEBHOOK_SECRET))
        ->store($request, new StripeWebhookEvents, $syncQueue ?? $this->syncQueue, new ApiResponder($this->logger));
});

describe('a genuine event', function () {
    it('acknowledges it', function () {
        $response = ($this->receive)(signedStripeWebhookRequest(stripeWebhookPayload()));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['received' => true]);
    });

    it('schedules one sync of the subscription it concerns', function () {
        ($this->receive)(signedStripeWebhookRequest(stripeWebhookPayload()));

        expect($this->syncQueue->scheduled)->toBe([SubscriptionFixtures::BILLING_SUBSCRIPTION_ID]);
    });

    it('acknowledges an event it has no use for, scheduling nothing', function () {
        $response = ($this->receive)(signedStripeWebhookRequest(stripeWebhookPayload(Event::CUSTOMER_CREATED)));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['received' => true])
            ->and($this->syncQueue->scheduled)->toBe([]);
    });

    it('answers a failure to schedule as a server error, so the billing provider retries', function () {
        $response = ($this->receive)(
            signedStripeWebhookRequest(stripeWebhookPayload()),
            new FakeSubscriptionSyncQueue(new RuntimeException('The queue is down.')),
        );

        expect($response->getStatusCode())->toBe(500);
        $this->logger->shouldHaveReceived('error')->once();
    });
});

describe('an event that cannot be trusted', function () {
    it('rejects it without scheduling anything', function (Closure $request) {
        $response = ($this->receive)($request());

        expect($response->getStatusCode())->toBe(400)
            ->and($response->getData(true))->toBe(['received' => false])
            ->and($this->syncQueue->scheduled)->toBe([]);
    })->with([
        'signed with another secret' => fn () => signedStripeWebhookRequest(stripeWebhookPayload(), 'whsec_test_SomebodyElse'),
        'with no signature' => fn () => stripeWebhookRequest(stripeWebhookPayload(), null),
        'with a malformed signature' => fn () => stripeWebhookRequest(stripeWebhookPayload(), 'not-a-signature'),
        'altered after signing' => fn () => stripeWebhookRequest(
            str_replace(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID, SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID, stripeWebhookPayload()),
            WebhookSignature::generateSignatureHeader(stripeWebhookPayload(), STRIPE_WEBHOOK_SECRET),
        ),
        'signed too long ago to rule out a replay' => fn () => signedStripeWebhookRequest(
            stripeWebhookPayload(),
            timestamp: time() - STRIPE_WEBHOOK_STALE_SECONDS,
        ),
        'signed but not json' => fn () => signedStripeWebhookRequest('{not json'),
    ]);
});
