<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessContext;

/**
 * A BusinessContext pinned to one tenant.
 *
 * This is what the port exists for: injecting a known business uuid is how a
 * unit test asserts tenant isolation - that the uuid reaching the entity, the
 * repository and the output DTO is the current tenant's and nobody else's -
 * with no container, no middleware and no database.
 *
 * Onboarding deliberately has no consumer for it: OnboardBusiness and
 * RegisterBusinessOwner are the pair that brings a tenant into existence, so
 * there is no current business for them to read. Every other tenant-scoped use
 * case takes one.
 */
final class FakeBusinessContext implements BusinessContext
{
    public const BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';

    public function __construct(
        private readonly string $businessId = self::BUSINESS_ID,
    ) {}

    public function currentBusinessId(): string
    {
        return $this->businessId;
    }
}
