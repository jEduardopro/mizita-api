<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\BillingContacts;
use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;
use App\Domains\Subscriptions\ValueObjects\BillingContact;

final class FakeBillingContacts implements BillingContacts
{
    /** @var array<string, BillingContact> */
    private array $contacts = [];

    /** @var list<string> */
    public array $lookups = [];

    public function __construct(BillingContact ...$contacts)
    {
        foreach ($contacts as $contact) {
            $this->contacts[$contact->businessId] = $contact;
        }
    }

    public function ownerOf(string $businessId): BillingContact
    {
        $this->lookups[] = $businessId;

        return $this->contacts[$businessId] ?? throw SubscriptionBusinessNotFound::withId($businessId);
    }
}
