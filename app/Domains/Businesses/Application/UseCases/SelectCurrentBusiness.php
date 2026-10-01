<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\SelectCurrentBusinessInput;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessSelection;
use App\Shared\Contracts\CurrentBusinessResolver;
use App\Shared\Contracts\DomainFailure;

final class SelectCurrentBusiness
{
    public function __construct(
        private readonly CurrentBusinessResolver $currentBusiness,
        private readonly BusinessSelection $selection,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(SelectCurrentBusinessInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->currentBusiness->resolveFor($input->accountId, $input->businessId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->selection->rememberFor($input->accountId, $businessId);

        return UseCaseResponse::success();
    }
}
