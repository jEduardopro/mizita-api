<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\ValueObjects\BillingContact;

interface BillingCustomers
{
    public function create(BillingContact $contact): string;
}
