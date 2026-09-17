<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\UseCases;

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\UpdateCustomerInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\Services\ContactUniqueness;
use App\Domains\Customers\Application\Services\SubmittedPhoneNumber;
use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Domains\Customers\Exceptions\CustomerPhoneAlreadyTaken;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class UpdateCustomer
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerPhoneBook $phones,
        private readonly CustomerAddressBook $addresses,
        private readonly CustomerPresenter $presenter,
        private readonly SubmittedPhoneNumber $submittedPhone,
        private readonly ContactUniqueness $uniqueness,
        private readonly Clock $clock,
        private readonly BusinessContext $business,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<CustomerData>
     */
    public function handle(UpdateCustomerInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $customer = $this->customers->findForBusiness($businessId, $input->customerId);

            $this->transactions->run(function () use ($input, $customer, $businessId): void {
                $this->apply($input, $customer, $businessId);
            });

            return UseCaseResponse::success($this->presenter->describe($customer));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws CustomerEmailAlreadyTaken
     * @throws CustomerPhoneAlreadyTaken
     */
    private function apply(UpdateCustomerInput $input, Customer $customer, string $businessId): void
    {
        $email = CustomerEmail::fromNullable($input->email);
        $phone = $this->submittedPhone->parse($input->phone);

        $this->uniqueness->ensureEmailIsFree($businessId, $email, $customer->id);
        $this->uniqueness->ensurePhoneIsFree($businessId, $phone, $customer->id);

        $customer->rename($input->name);
        $customer->changeEmail($email);
        $customer->changeBirthDate($input->toBirthDate(), $this->clock->now());
        $customer->changeNotes($input->notes);

        $this->customers->save($customer);
        $this->phones->replaceForCustomer($customer->id, $phone);
        $this->relocate($customer->id, $input->address);
    }

    private function relocate(string $customerId, ?CustomerAddressSnapshot $address): void
    {
        if ($address === null) {
            return;
        }

        $this->addresses->replaceForCustomer($customerId, $address);
    }
}
