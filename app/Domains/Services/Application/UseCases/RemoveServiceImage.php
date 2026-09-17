<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\RemoveServiceImageInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class RemoveServiceImage
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceImages $images,
        private readonly ServicePresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<ServiceData>
     */
    public function handle(RemoveServiceImageInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $service = $this->services->findForBusiness($businessId, $input->serviceId);

            $this->images->remove($businessId, $service->id);

            return UseCaseResponse::success($this->presenter->describe($service));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
