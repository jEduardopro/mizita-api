<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\TeamQuery;
use App\Shared\ValueObjects\Paginated;

interface TeamRoster
{
    /**
     * @return Paginated<StaffMember>
     */
    public function search(string $businessId, TeamQuery $query): Paginated;

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public function emailsAlreadyOnTeam(string $businessId, array $emails): array;
}
