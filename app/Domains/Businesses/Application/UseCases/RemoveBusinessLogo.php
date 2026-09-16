<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class RemoveBusinessLogo
{
    public function __construct(
        private readonly BusinessLogo $logo,
        private readonly BusinessSettingsPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<BusinessSettingsData>
     */
    public function handle(): UseCaseResponse
    {
        try {
            $businessId = $this->business->currentBusinessId();

            $this->logo->remove($businessId);

            return UseCaseResponse::success($this->presenter->describe($businessId));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
