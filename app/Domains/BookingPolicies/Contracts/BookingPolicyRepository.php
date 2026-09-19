<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Contracts;

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\Exceptions\BookingPolicyNotFound;

interface BookingPolicyRepository
{
    public function findForBusiness(string $businessId): ?BookingPolicy;

    public function save(BookingPolicy $policy): void;

    /**
     * @throws BookingPolicyNotFound
     */
    public function delete(string $id): void;
}
