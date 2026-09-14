<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\UseCases;

use App\Domains\Customers\Application\Dtos\CreateCustomerInput;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Events\CustomerCreated;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Contracts\Events\Dispatcher;

final class CreateCustomer
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly BusinessContext $business,
        private readonly Dispatcher $events,
    ) {}

    public function handle(CreateCustomerInput $input): CustomerData
    {
        $customer = Customer::create(
            id: $this->ids->next(),
            businessId: $this->business->currentBusinessId(),
            name: $input->name,
            email: $input->email,
            phone: $input->phone,
            now: $this->clock->now(),
        );

        $this->customers->save($customer);
        $this->events->dispatch(new CustomerCreated($customer->id));

        return CustomerData::fromEntity($customer);
    }
}
