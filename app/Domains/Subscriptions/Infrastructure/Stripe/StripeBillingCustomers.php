<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use App\Domains\Subscriptions\Contracts\BillingCustomers;
use App\Domains\Subscriptions\ValueObjects\BillingContact;

final class StripeBillingCustomers implements BillingCustomers
{
    public const BUSINESS_METADATA_KEY = 'business_id';

    public function __construct(
        private readonly StripeApi $stripe,
    ) {}

    public function create(BillingContact $contact): string
    {
        return $this->stripe->createCustomer([
            'name' => $contact->name,
            'email' => $contact->email,
            'metadata' => [self::BUSINESS_METADATA_KEY => $contact->businessId],
        ])->id;
    }
}
