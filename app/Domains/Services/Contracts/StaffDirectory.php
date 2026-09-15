<?php

declare(strict_types=1);

namespace App\Domains\Services\Contracts;

use App\Domains\Services\ValueObjects\StaffMemberSnapshot;

interface StaffDirectory
{
    /**
     * @param  list<string>  $staffIds
     * @return list<StaffMemberSnapshot>
     */
    public function membersOf(string $businessId, array $staffIds): array;
}
