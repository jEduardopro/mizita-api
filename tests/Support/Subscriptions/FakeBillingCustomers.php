<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\BillingCustomers;
use App\Domains\Subscriptions\ValueObjects\BillingContact;

final class FakeBillingCustomers implements BillingCustomers
{
    /** @var list<BillingContact> */
    public array $created = [];

    public function __construct(
        private readonly string $customerId = SubscriptionFixtures::CREATED_BILLING_CUSTOMER_ID,
    ) {}

    public function create(BillingContact $contact): string
    {
        $this->created[] = $contact;

        return $this->customerId;
    }
}
