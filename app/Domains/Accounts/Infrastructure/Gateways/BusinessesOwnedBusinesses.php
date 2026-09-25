<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Gateways;

use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Domains\Accounts\ValueObjects\ClosedBusinessSnapshot;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;
use App\Domains\Businesses\Application\Dtos\CloseBusinessInput;
use App\Domains\Businesses\Application\Dtos\ReopenBusinessInput;
use App\Domains\Businesses\Application\UseCases\CloseBusiness;
use App\Domains\Businesses\Application\UseCases\ReopenBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessAlreadyClosed;
use App\Domains\Businesses\Exceptions\BusinessNotClosed;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use LogicException;

final class BusinessesOwnedBusinesses implements OwnedBusinesses
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly CloseBusiness $closeBusiness,
        private readonly ReopenBusiness $reopenBusiness,
    ) {}

    /**
     * @throws BusinessNotFound
     */
    public function describe(string $businessId): OwnedBusinessSnapshot
    {
        $business = $this->businesses->findById($businessId);

        return new OwnedBusinessSnapshot(
            id: $business->id,
            name: $business->name(),
        );
    }

    /**
     * @throws BusinessNotFound
     * @throws BusinessAlreadyClosed
     */
    public function close(string $businessId, string $ownerAccountId): void
    {
        $this->closeBusiness->handle(new CloseBusinessInput(
            businessId: $businessId,
            ownerAccountId: $ownerAccountId,
        ))->value();
    }

    public function closedBusinessOf(string $accountId): ?ClosedBusinessSnapshot
    {
        $business = $this->businesses->findClosedOwnedBy($accountId);

        if ($business === null) {
            return null;
        }

        $closedAt = $business->closedAt();
        $purgeScheduledAt = $business->purgeScheduledAt();

        if ($closedAt === null || $purgeScheduledAt === null) {
            throw new LogicException("Business [{$business->id}] was returned as closed without a closing instant.");
        }

        return new ClosedBusinessSnapshot(
            id: $business->id,
            name: $business->name(),
            closedAt: $closedAt,
            purgeScheduledAt: $purgeScheduledAt,
            purged: $business->isPurged(),
        );
    }

    /**
     * @throws BusinessNotClosed
     * @throws OwnerAlreadyHasBusiness
     */
    public function reopen(string $businessId, string $ownerAccountId): void
    {
        $this->reopenBusiness->handle(new ReopenBusinessInput(
            businessId: $businessId,
            ownerAccountId: $ownerAccountId,
        ))->value();
    }
}
