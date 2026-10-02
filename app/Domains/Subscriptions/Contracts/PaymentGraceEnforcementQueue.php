<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

interface PaymentGraceEnforcementQueue
{
    public function schedule(string $businessId): void;
}
