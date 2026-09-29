<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\BillingCheckout;
use App\Domains\Subscriptions\Exceptions\CheckoutSessionNotFound;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\CheckoutRequest;
use App\Domains\Subscriptions\ValueObjects\CheckoutSession;

final class FakeBillingCheckout implements BillingCheckout
{
    public const SESSION_ID = 'cs_test_Session0000000000000001';

    public const CLIENT_SECRET = 'cs_test_Session0000000000000001_secret_Test';

    /** @var array<string, array{billingCustomerId: string, snapshot: ?BillingSnapshot}> */
    private array $sessions = [];

    /** @var list<CheckoutRequest> */
    public array $started = [];

    /** @var list<array{sessionId: string, billingCustomerId: string}> */
    public array $lookups = [];

    public function withSession(string $sessionId, string $billingCustomerId, ?BillingSnapshot $snapshot): self
    {
        $this->sessions[$sessionId] = ['billingCustomerId' => $billingCustomerId, 'snapshot' => $snapshot];

        return $this;
    }

    public function start(CheckoutRequest $request): CheckoutSession
    {
        $this->started[] = $request;

        return new CheckoutSession(self::SESSION_ID, self::CLIENT_SECRET);
    }

    public function subscriptionOf(string $sessionId, string $billingCustomerId): ?BillingSnapshot
    {
        $this->lookups[] = ['sessionId' => $sessionId, 'billingCustomerId' => $billingCustomerId];

        $session = $this->sessions[$sessionId] ?? null;

        if ($session === null || $session['billingCustomerId'] !== $billingCustomerId) {
            throw CheckoutSessionNotFound::withId($sessionId);
        }

        return $session['snapshot'];
    }
}
