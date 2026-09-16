<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;

interface PublishedTeam
{
    /**
     * @return list<PublicTeamMember>
     */
    public function forBusiness(string $businessId): array;
}
