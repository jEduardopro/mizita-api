<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\Exceptions\BusinessHasNoOwner;
use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Domains\Platform\Exceptions\ImpersonatedBusinessNotFound;
use App\Domains\Platform\ValueObjects\BusinessOwnerAccount;

interface BusinessOwnerAccounts
{
    /**
     * @throws ImpersonatedBusinessNotFound
     * @throws BusinessHasNoOwner
     * @throws BusinessOwnerDeactivated
     */
    public function ownerOf(string $businessId): BusinessOwnerAccount;
}
