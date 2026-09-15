<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\AttachServiceImageInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class AttachServiceImage
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
    public function handle(AttachServiceImageInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $service = $this->services->findForBusiness($businessId, $input->serviceId);

            $this->images->replace($service->id, $input->sourcePath, $input->fileName);

            return UseCaseResponse::success($this->presenter->describe($businessId, $service));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
