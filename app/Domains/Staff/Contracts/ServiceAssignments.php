<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

interface ServiceAssignments
{
    /**
     * @param  list<string>  $staffMemberIds
     * @return list<string>
     */
    public function staffOfferingServices(string $businessId, array $staffMemberIds): array;
}
