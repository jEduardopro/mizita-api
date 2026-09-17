<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\UseCases;

use App\Domains\Customers\Application\Dtos\DeleteCustomerInput;
use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerPhotos;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class DeleteCustomer
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerPhoneBook $phones,
        private readonly CustomerAddressBook $addresses,
        private readonly CustomerPhotos $photos,
        private readonly BusinessContext $business,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(DeleteCustomerInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $this->transactions->run(function () use ($businessId, $input): void {
                $this->erase($businessId, $input->customerId);
            });

            return UseCaseResponse::success();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws CustomerNotFound
     */
    private function erase(string $businessId, string $customerId): void
    {
        $customer = $this->customers->findForBusiness($businessId, $customerId);

        $this->removeBelongingsWhileCustomerIsStillResolvable($businessId, $customer->id);

        $this->customers->delete($businessId, $customer->id);
    }

    private function removeBelongingsWhileCustomerIsStillResolvable(string $businessId, string $customerId): void
    {
        $this->phones->removeForCustomer($customerId);
        $this->addresses->removeForCustomer($customerId);
        $this->photos->remove($businessId, $customerId);
    }
}
