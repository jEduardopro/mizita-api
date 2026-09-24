<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\ValueObjects\OwnerRegistration;
use App\Domains\Staff\Application\Dtos\RegisterBusinessOwnerInput;
use App\Domains\Staff\Application\UseCases\RegisterBusinessOwner;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;

final class StaffOwnerRegistrar implements OwnerRegistrar
{
    public function __construct(
        private readonly RegisterBusinessOwner $registerBusinessOwner,
    ) {}

    /**
     * @throws OwnerAlreadyHasBusiness
     */
    public function registerOwner(string $businessId, string $accountId): OwnerRegistration
    {
        try {
            $registration = $this->registerBusinessOwner->handle(new RegisterBusinessOwnerInput(
                businessId: $businessId,
                accountId: $accountId,
            ))->value();
        } catch (AccountAlreadyOwnsBusiness $conflict) {
            throw OwnerAlreadyHasBusiness::forAccount($accountId, $conflict);
        }

        return new OwnerRegistration(
            staffMemberId: $registration->member->id,
            events: $registration->events,
        );
    }
}
