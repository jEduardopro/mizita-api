<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Shared\ValueObjects\PhoneNumber;

/**
 * Input boundary for OnboardBusiness. Framework free: the controller maps
 * the HTTP request into this object.
 *
 * There is no slug field. The address is derived from the name, so accepting
 * one here would be accepting a second, contradictory source for it.
 *
 * ownerAccountId is the authenticated caller, read from the session by the
 * controller. It is never taken from the request body: a client that can name
 * the owner is a client that can hand a business to somebody else.
 */
final readonly class OnboardBusinessInput
{
    public function __construct(
        public string $ownerAccountId,
        public string $name,
        public string $timezone,
        public string $industryId,
        public ?PhoneNumber $phone = null,
    ) {}
}
