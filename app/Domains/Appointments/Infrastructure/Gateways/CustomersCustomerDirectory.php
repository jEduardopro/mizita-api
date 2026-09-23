<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\Exceptions\InvalidGuestEmail;
use App\Domains\Appointments\Exceptions\InvalidGuestName;
use App\Domains\Appointments\Exceptions\InvalidGuestPhone;
use App\Domains\Appointments\ValueObjects\CustomerPhoneSnapshot;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\GuestAddress;
use App\Domains\Appointments\ValueObjects\GuestContact;
use App\Domains\Appointments\ValueObjects\GuestPhone;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Application\Dtos\GuestAddressInput;
use App\Domains\Customers\Application\Dtos\GuestContactInput;
use App\Domains\Customers\Application\Services\GuestCustomerRegistrar;
use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerAddress;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Shared\ValueObjects\PhoneNumber;

final class CustomersCustomerDirectory implements CustomerDirectory
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly GuestCustomerRegistrar $guests,
        private readonly CustomerPhoneBook $phones,
    ) {}

    public function describe(string $businessId, string $customerId): CustomerSnapshot
    {
        return $this->describeMany($businessId, [$customerId])[$customerId]
            ?? throw AppointmentCustomerNotFound::withId($customerId);
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, CustomerSnapshot>
     */
    public function describeMany(string $businessId, array $customerIds): array
    {
        $selected = $this->customers->findManyIncludingArchived(
            $businessId,
            array_values(array_unique($customerIds)),
        );

        if ($selected === []) {
            return [];
        }

        $numbers = $this->numbersByCustomerId($selected);

        $snapshots = [];

        foreach ($selected as $customer) {
            $snapshots[$customer->id] = self::snapshotOf($customer, $numbers[$customer->id] ?? null);
        }

        return $snapshots;
    }

    public function findOrCreateGuest(string $businessId, GuestContact $guest): CustomerSnapshot
    {
        return self::snapshotOfRegistration($this->registered($businessId, $guest));
    }

    /**
     * @throws InvalidGuestName
     * @throws InvalidGuestEmail
     * @throws InvalidGuestPhone
     * @throws InvalidGuestAddress
     */
    private function registered(string $businessId, GuestContact $guest): CustomerData
    {
        try {
            return $this->guests->register($businessId, self::contactFrom($guest));
        } catch (InvalidCustomerName $rejected) {
            throw InvalidGuestName::rejected($rejected);
        } catch (InvalidCustomerEmail $rejected) {
            throw InvalidGuestEmail::rejected($rejected);
        } catch (InvalidCustomerPhone $rejected) {
            throw InvalidGuestPhone::rejected($rejected);
        } catch (InvalidCustomerAddress $rejected) {
            throw InvalidGuestAddress::rejected($rejected);
        }
    }

    /**
     * @param  list<Customer>  $customers
     * @return array<string, PhoneNumber>
     */
    private function numbersByCustomerId(array $customers): array
    {
        return $this->phones->forCustomers(array_map(
            static fn (Customer $customer): string => $customer->id,
            $customers,
        ));
    }

    private static function contactFrom(GuestContact $guest): GuestContactInput
    {
        return new GuestContactInput(
            name: $guest->name,
            email: $guest->email,
            phone: self::phoneInputFrom($guest->phone),
            notes: null,
            address: self::addressInputFrom($guest->address),
        );
    }

    private static function phoneInputFrom(?GuestPhone $phone): ?CustomerPhoneInput
    {
        if ($phone === null) {
            return null;
        }

        return new CustomerPhoneInput($phone->countryCode, $phone->nationalNumber);
    }

    private static function addressInputFrom(?GuestAddress $address): ?GuestAddressInput
    {
        if ($address === null) {
            return null;
        }

        return new GuestAddressInput(
            street: $address->street,
            city: $address->city,
            stateName: $address->stateName,
            postalCode: $address->postalCode,
            countryCode: $address->countryCode,
        );
    }

    private static function snapshotOf(Customer $customer, ?PhoneNumber $phone): CustomerSnapshot
    {
        return new CustomerSnapshot(
            id: $customer->id,
            name: $customer->name(),
            email: $customer->email()?->value,
            phone: self::phoneSnapshotOf($phone),
        );
    }

    private static function snapshotOfRegistration(CustomerData $customer): CustomerSnapshot
    {
        return new CustomerSnapshot(
            id: $customer->id,
            name: $customer->name,
            email: $customer->email,
            phone: self::phoneSnapshotOf($customer->phone),
        );
    }

    private static function phoneSnapshotOf(?PhoneNumber $phone): ?CustomerPhoneSnapshot
    {
        if ($phone === null) {
            return null;
        }

        return new CustomerPhoneSnapshot(
            countryCode: $phone->country()->value,
            nationalNumber: $phone->nationalNumber(),
        );
    }
}
