<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\ValueObjects\PlatformBusinessQuery;
use App\Domains\Platform\ValueObjects\PlatformBusinessRecord;
use App\Shared\ValueObjects\Paginated;

interface PlatformBusinessDirectory
{
    /**
     * @return Paginated<PlatformBusinessRecord>
     */
    public function page(PlatformBusinessQuery $query): Paginated;
}
