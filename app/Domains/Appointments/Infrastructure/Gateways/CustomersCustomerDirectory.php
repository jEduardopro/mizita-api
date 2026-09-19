<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\InvalidGuestEmail;
use App\Domains\Appointments\Exceptions\InvalidGuestName;
use App\Domains\Appointments\Exceptions\InvalidGuestPhone;
use App\Domains\Appointments\Exceptions\MissingGuestContactChannel;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\GuestContact;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Application\Dtos\GuestContactInput;
use App\Domains\Customers\Application\Services\GuestCustomerRegistrar;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\Exceptions\InvalidGuestContact;

final class CustomersCustomerDirectory implements CustomerDirectory
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly GuestCustomerRegistrar $guests,
    ) {}

    public function describe(string $businessId, string $customerId): CustomerSnapshot
    {
        try {
            return self::snapshotOf($this->customers->findIncludingArchived($businessId, $customerId));
        } catch (CustomerNotFound $missing) {
            throw AppointmentCustomerNotFound::withId($customerId, $missing);
        }
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, CustomerSnapshot>
     */
    public function describeMany(string $businessId, array $customerIds): array
    {
        $snapshots = [];

        foreach (array_unique($customerIds) as $customerId) {
            $snapshots[$customerId] = $this->describe($businessId, $customerId);
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
     * @throws MissingGuestContactChannel
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
        } catch (InvalidGuestContact $rejected) {
            throw MissingGuestContactChannel::forGuest($rejected);
        }
    }

    private static function contactFrom(GuestContact $guest): GuestContactInput
    {
        $phone = $guest->phone;

        return new GuestContactInput(
            name: $guest->name,
            email: $guest->email,
            phone: $phone === null
                ? null
                : new CustomerPhoneInput($phone->countryCode, $phone->nationalNumber),
            notes: null,
        );
    }

    private static function snapshotOf(Customer $customer): CustomerSnapshot
    {
        return new CustomerSnapshot(
            id: $customer->id,
            name: $customer->name(),
            email: $customer->email()?->value,
        );
    }

    private static function snapshotOfRegistration(CustomerData $customer): CustomerSnapshot
    {
        return new CustomerSnapshot(
            id: $customer->id,
            name: $customer->name,
            email: $customer->email,
        );
    }
}
