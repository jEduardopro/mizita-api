<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

/**
 * One call, made inside the same transaction as the business itself: a business
 * whose roles were never created is one where the first membership cannot be
 * assigned at all.
 *
 * The signature names no role, no permission and no package - which roles a
 * business gets is the catalogue's decision.
 */
interface RoleProvisioner
{
    /**
     * Idempotent, so a retried signup does not duplicate roles. Raises when the
     * business does not exist; the exception class is left to the implementation.
     */
    public function provisionFor(string $businessId): void;
}
