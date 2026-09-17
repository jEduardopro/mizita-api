<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\UseCases;

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\ShowCustomerInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ShowCustomer
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<CustomerData>
     */
    public function handle(ShowCustomerInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $customer = $this->customers->findForBusiness(
                $this->business->currentBusinessId(),
                $input->customerId,
            );

            return UseCaseResponse::success($this->presenter->describe($customer));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
