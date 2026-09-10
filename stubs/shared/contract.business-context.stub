<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Supplies the business (tenant) the current request belongs to.
 *
 * This is the only tenancy interface the domain layer sees. It returns the
 * business uuid, which is what tenant-scoped entities carry as businessId.
 */
interface BusinessContext
{
    public function currentBusinessId(): string;
}
