<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Staff\Application\Dtos\RegisterBusinessOwnerInput;
use App\Domains\Staff\Application\UseCases\RegisterBusinessOwner;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;

/**
 * Translating AccountAlreadyOwnsBusiness into this domain's
 * OwnerAlreadyHasBusiness is the point of the class: a boundary that lets the
 * neighbour's exception through is decorative, because the use case above would
 * catch a Staff class and the HTTP edge would render a Staff error code.
 *
 * The registration DTO is dropped on purpose - Businesses has no use for a
 * staff member - and only the events travel on, as opaque objects.
 */
final class StaffOwnerRegistrar implements OwnerRegistrar
{
    public function __construct(
        private readonly RegisterBusinessOwner $registerBusinessOwner,
    ) {}

    /**
     * @return list<object>
     *
     * @throws OwnerAlreadyHasBusiness
     */
    public function registerOwner(string $businessId, string $accountId): array
    {
        try {
            return $this->registerBusinessOwner->handle(new RegisterBusinessOwnerInput(
                businessId: $businessId,
                accountId: $accountId,
            ))->events;
        } catch (AccountAlreadyOwnsBusiness $conflict) {
            throw OwnerAlreadyHasBusiness::forAccount($accountId, $conflict);
        }
    }
}
