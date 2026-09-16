<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\AttachBusinessLogoInput;
use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class AttachBusinessLogo
{
    public function __construct(
        private readonly BusinessLogo $logo,
        private readonly BusinessSettingsPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<BusinessSettingsData>
     */
    public function handle(AttachBusinessLogoInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $this->logo->replace($businessId, $input->sourcePath, $input->fileName);

            return UseCaseResponse::success($this->presenter->describe($businessId));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
