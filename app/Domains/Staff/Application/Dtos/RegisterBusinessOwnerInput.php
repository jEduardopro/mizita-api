<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

/**
 * Input boundary for RegisterBusinessOwner.
 *
 * businessId is an argument rather than something read from BusinessContext,
 * and that is the point of this use case: the row it writes is what will later
 * resolve the tenant, so at this moment there is no tenant to read. Both values
 * are uuids.
 */
final readonly class RegisterBusinessOwnerInput
{
    public function __construct(
        public string $businessId,
        public string $accountId,
    ) {}
}
