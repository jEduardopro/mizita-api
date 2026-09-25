<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\PurgeClosedBusinessInput;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\TenantDataEraser;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNotClosed;
use App\Domains\Businesses\Exceptions\BusinessNotDueForPurge;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class PurgeClosedBusiness
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly TenantDataEraser $eraser,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(PurgeClosedBusinessInput $input): UseCaseResponse
    {
        try {
            $this->purge($input->businessId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }

    /**
     * @throws BusinessNotClosed
     * @throws BusinessNotDueForPurge
     */
    private function purge(string $businessId): void
    {
        $business = $this->closedBusiness($businessId);

        if ($business->isPurged()) {
            return;
        }

        $business->markPurged($this->clock->now());

        $this->eraser->eraseFilesOf($business->id);

        $this->transactions->run(function () use ($business): void {
            $this->eraser->eraseRecordsOf($business->id);
            $this->businesses->save($business);
        });
    }

    /**
     * @throws BusinessNotClosed
     */
    private function closedBusiness(string $businessId): Business
    {
        $business = $this->businesses->findClosedById($businessId);

        if ($business === null) {
            throw BusinessNotClosed::withId($businessId);
        }

        return $business;
    }
}
