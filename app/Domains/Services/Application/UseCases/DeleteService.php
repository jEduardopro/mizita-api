<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\DeleteServiceInput;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class DeleteService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(DeleteServiceInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $this->services->delete($this->business->currentBusinessId(), $input->serviceId);

            return UseCaseResponse::success();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
