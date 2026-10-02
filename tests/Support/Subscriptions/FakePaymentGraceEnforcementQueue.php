<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\PaymentGraceEnforcementQueue;

final class FakePaymentGraceEnforcementQueue implements PaymentGraceEnforcementQueue
{
    /** @var list<string> */
    public array $scheduled = [];

    public function schedule(string $businessId): void
    {
        $this->scheduled[] = $businessId;
    }
}
