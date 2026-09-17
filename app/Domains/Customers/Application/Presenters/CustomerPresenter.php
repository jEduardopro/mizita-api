<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Presenters;

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerPhotos;
use App\Domains\Customers\Entities\Customer;
use App\Shared\ValueObjects\Paginated;

final class CustomerPresenter
{
    public function __construct(
        private readonly CustomerPhoneBook $phones,
        private readonly CustomerAddressBook $addresses,
        private readonly CustomerPhotos $photos,
    ) {}

    public function describe(Customer $customer): CustomerData
    {
        return CustomerData::fromEntity(
            $customer,
            $this->phones->forCustomer($customer->id),
            $this->addresses->forCustomer($customer->id),
            $this->photos->urlFor($customer->businessId, $customer->id),
        );
    }

    /**
     * @param  Paginated<Customer>  $page
     * @return Paginated<CustomerData>
     */
    public function describePage(string $businessId, Paginated $page): Paginated
    {
        $customerIds = array_map(
            static fn (Customer $customer): string => $customer->id,
            $page->items,
        );

        $numbers = $this->phones->forCustomers($customerIds);
        $photoUrls = $this->photos->urlsFor($businessId, $customerIds);

        return $page->map(static fn (Customer $customer): CustomerData => CustomerData::fromEntity(
            $customer,
            $numbers[$customer->id] ?? null,
            null,
            $photoUrls[$customer->id] ?? null,
        ));
    }
}
