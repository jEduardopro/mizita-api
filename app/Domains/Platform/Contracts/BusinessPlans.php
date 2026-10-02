<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\ValueObjects\Plan;
use DateTimeImmutable;

interface BusinessPlans
{
    /**
     * @param  list<string>  $businessIds
     * @return array<string, Plan>
     */
    public function plansOf(array $businessIds, DateTimeImmutable $now): array;
}
