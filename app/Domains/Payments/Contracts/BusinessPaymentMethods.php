<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

interface BusinessPaymentMethods
{
    public function enableDefaultsFor(string $businessId): void;
}
