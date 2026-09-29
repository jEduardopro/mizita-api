<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPlan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;

interface PlanCatalog
{
    /**
     * @throws InvalidSubscriptionPlan
     */
    public function findById(string $id): PlanOffer;

    /**
     * @return list<PlanOffer>
     */
    public function all(): array;
}
