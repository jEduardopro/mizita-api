<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Services;

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\GuestAddressInput;
use App\Domains\Customers\Application\Dtos\GuestContactInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Domains\Customers\Exceptions\InvalidCustomerAddress;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\PhoneNumber;

final class GuestCustomerRegistrar
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerPhoneBook $phones,
        private readonly CustomerAddressBook $addresses,
        private readonly CustomerPresenter $presenter,
        private readonly SubmittedPhoneNumber $submittedPhone,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @throws InvalidCustomerName
     * @throws InvalidCustomerEmail
     * @throws InvalidCustomerPhone
     * @throws InvalidCustomerNotes
     * @throws InvalidCustomerAddress
     * @throws CustomerEmailAlreadyTaken
     */
    public function register(string $businessId, GuestContactInput $contact): CustomerData
    {
        $contact->validate();

        $email = CustomerEmail::fromNullable($contact->email);
        $phone = $this->submittedPhone->parse($contact->phone);

        $known = $this->knownCustomer($businessId, $email, $phone);

        if ($known !== null) {
            $this->fillMissingAddress($known->id, $contact->address);

            return $this->presenter->describe($known);
        }

        return $this->presenter->describe(
            $this->transactions->run(
                fn (): Customer => $this->enroll($businessId, $contact, $email, $phone),
            ),
        );
    }

    private function knownCustomer(string $businessId, ?CustomerEmail $email, ?PhoneNumber $phone): ?Customer
    {
        $byEmail = $this->matchingEmail($businessId, $email);

        if ($byEmail !== null) {
            return $byEmail;
        }

        return $this->matchingPhone($businessId, $phone);
    }

    private function matchingEmail(string $businessId, ?CustomerEmail $email): ?Customer
    {
        if ($email === null) {
            return null;
        }

        return $this->customers->findByEmail($businessId, $email);
    }

    private function matchingPhone(string $businessId, ?PhoneNumber $phone): ?Customer
    {
        if ($phone === null) {
            return null;
        }

        return $this->customers->findFirstAmong(
            $businessId,
            $this->phones->customerIdsWithNumber($phone),
        );
    }

    /**
     * @throws InvalidCustomerName
     * @throws InvalidCustomerNotes
     * @throws CustomerEmailAlreadyTaken
     */
    private function enroll(
        string $businessId,
        GuestContactInput $contact,
        ?CustomerEmail $email,
        ?PhoneNumber $phone,
    ): Customer {
        $customer = Customer::create(
            id: $this->ids->next(),
            businessId: $businessId,
            name: $contact->name,
            email: $email,
            birthDate: null,
            notes: $contact->notes,
            now: $this->clock->now(),
        );

        $this->customers->save($customer);
        $this->phones->replaceForCustomer($customer->id, $phone);
        $this->recordAddress($customer->id, $contact->address);

        return $customer;
    }

    private function fillMissingAddress(string $customerId, ?GuestAddressInput $address): void
    {
        if ($address === null || $this->addresses->forCustomer($customerId) !== null) {
            return;
        }

        $this->addresses->replaceForCustomer($customerId, $address->toSnapshot());
    }

    private function recordAddress(string $customerId, ?GuestAddressInput $address): void
    {
        if ($address === null) {
            return;
        }

        $this->addresses->replaceForCustomer($customerId, $address->toSnapshot());
    }
}
