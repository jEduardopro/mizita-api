<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

interface WorkingHours
{
    /**
     * @param  list<string>  $staffMemberIds
     * @return list<string>
     */
    public function staffWithWorkingHours(string $businessId, array $staffMemberIds): array;
}
