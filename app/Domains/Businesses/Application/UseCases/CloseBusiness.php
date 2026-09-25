<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\CloseBusinessInput;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\TeamSignOut;
use App\Domains\Businesses\Exceptions\BusinessAlreadyClosed;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class CloseBusiness
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly TeamSignOut $teamSignOut,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(CloseBusinessInput $input): UseCaseResponse
    {
        try {
            $this->close($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }

    /**
     * @throws BusinessNotFound
     * @throws BusinessAlreadyClosed
     */
    private function close(CloseBusinessInput $input): void
    {
        $business = $this->businesses->findById($input->businessId);

        $business->close($input->ownerAccountId, $this->clock->now());

        $this->transactions->run(function () use ($business): void {
            $this->teamSignOut->signOutTeamOf($business->id);
            $this->businesses->save($business);
        });
    }
}
