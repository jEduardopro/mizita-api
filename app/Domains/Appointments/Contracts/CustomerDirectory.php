<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\Exceptions\InvalidGuestEmail;
use App\Domains\Appointments\Exceptions\InvalidGuestName;
use App\Domains\Appointments\Exceptions\InvalidGuestPhone;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\GuestContact;

interface CustomerDirectory
{
    /**
     * @throws AppointmentCustomerNotFound
     */
    public function describe(string $businessId, string $customerId): CustomerSnapshot;

    /**
     * @throws InvalidGuestName
     * @throws InvalidGuestEmail
     * @throws InvalidGuestPhone
     * @throws InvalidGuestAddress
     */
    public function findOrCreateGuest(string $businessId, GuestContact $guest): CustomerSnapshot;

    /**
     * @param  list<string>  $customerIds
     * @return array<string, CustomerSnapshot>
     */
    public function describeMany(string $businessId, array $customerIds): array;
}
