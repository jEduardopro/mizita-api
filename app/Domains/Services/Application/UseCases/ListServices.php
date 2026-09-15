<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\ListServicesInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Paginated;

final class ListServices
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServicePresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<Paginated<ServiceData>>
     */
    public function handle(ListServicesInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $page = $this->services->search($businessId, $input->toQuery());

            return UseCaseResponse::success($this->presenter->describePage($businessId, $page));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
