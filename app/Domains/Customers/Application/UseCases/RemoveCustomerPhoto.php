<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\UseCases;

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\RemoveCustomerPhotoInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Contracts\CustomerPhotos;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class RemoveCustomerPhoto
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerPhotos $photos,
        private readonly CustomerPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<CustomerData>
     */
    public function handle(RemoveCustomerPhotoInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $customer = $this->customers->findForBusiness($businessId, $input->customerId);

            $this->photos->remove($businessId, $customer->id);

            return UseCaseResponse::success($this->presenter->describe($customer));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
