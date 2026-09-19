<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use App\Domains\Customers\Application\Dtos\CreateCustomerInput;
use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Application\Dtos\GuestContactInput;
use App\Domains\Customers\Application\Dtos\UpdateCustomerInput;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\ValueObjects\BirthDate;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;

final class CustomerFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c1';

    public const SECOND_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c2';

    public const THIRD_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c3';

    public const GENERATED_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c9';

    public const FOREIGN_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000cf';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const STATE_ID = '01930000-0000-7000-8000-0000000000a1';

    public const NAME = 'Ada Lovelace';

    public const EMAIL = 'ada@example.com';

    public const BIRTH_DATE = '1990-05-04';

    public const NOTES = 'Prefiere cita por la mañana.';

    public const STREET = 'Av. Reforma 123';

    public const CITY = 'Ciudad de México';

    public const POSTAL_CODE = '06600';

    public const COUNTRY_CODE = 'MX';

    public const PHOTO_SOURCE_PATH = '/tmp/phpA1b2C3';

    public const PHOTO_FILE_NAME = 'ada.jpg';

    public const PHOTO_MIME_TYPE = 'image/jpeg';

    public const PHOTO_SIZE_IN_BYTES = 120_000;

    public const PHOTO_URL = 'https://cdn.mizita.test/customers/'.self::CUSTOMER_ID.'/ada.jpg';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function birthDate(string $value = self::BIRTH_DATE): DateTimeImmutable
    {
        return BirthDate::fromNullable($value) ?? self::now();
    }

    public static function customer(
        string $id = self::CUSTOMER_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        string $name = self::NAME,
        ?string $email = self::EMAIL,
        ?string $birthDate = self::BIRTH_DATE,
        ?string $notes = self::NOTES,
        ?DateTimeImmutable $createdAt = null,
    ): Customer {
        return Customer::restore(
            id: $id,
            businessId: $businessId,
            name: $name,
            email: $email === null ? null : CustomerEmail::restore($email),
            birthDate: $birthDate === null ? null : self::birthDate($birthDate),
            notes: $notes,
            createdAt: $createdAt ?? self::now(),
        );
    }

    public static function address(
        string $street = self::STREET,
        ?string $city = self::CITY,
        ?string $stateId = self::STATE_ID,
        ?string $postalCode = self::POSTAL_CODE,
        string $countryCode = self::COUNTRY_CODE,
    ): CustomerAddressSnapshot {
        return new CustomerAddressSnapshot(
            street: $street,
            city: $city,
            stateId: $stateId,
            postalCode: $postalCode,
            countryCode: $countryCode,
        );
    }

    public static function phoneInput(
        string $countryCode = self::COUNTRY_CODE,
        string $nationalNumber = PhoneNumbers::MX_NATIONAL_NUMBER,
    ): CustomerPhoneInput {
        return new CustomerPhoneInput(
            countryCode: $countryCode,
            nationalNumber: $nationalNumber,
        );
    }

    public static function createInput(
        string $name = self::NAME,
        ?string $email = self::EMAIL,
        ?CustomerPhoneInput $phone = new CustomerPhoneInput(self::COUNTRY_CODE, PhoneNumbers::MX_NATIONAL_NUMBER),
        ?string $birthDate = self::BIRTH_DATE,
        ?string $notes = self::NOTES,
        ?CustomerAddressSnapshot $address = new CustomerAddressSnapshot(
            self::STREET,
            self::CITY,
            self::STATE_ID,
            self::POSTAL_CODE,
            self::COUNTRY_CODE,
        ),
    ): CreateCustomerInput {
        return new CreateCustomerInput(
            name: $name,
            email: $email,
            phone: $phone,
            birthDate: $birthDate,
            notes: $notes,
            address: $address,
        );
    }

    public static function guestContact(
        string $name = self::NAME,
        ?string $email = self::EMAIL,
        ?CustomerPhoneInput $phone = new CustomerPhoneInput(self::COUNTRY_CODE, PhoneNumbers::MX_NATIONAL_NUMBER),
        ?string $notes = null,
    ): GuestContactInput {
        return new GuestContactInput(
            name: $name,
            email: $email,
            phone: $phone,
            notes: $notes,
        );
    }

    public static function updateInput(
        string $customerId = self::CUSTOMER_ID,
        string $name = self::NAME,
        ?string $email = self::EMAIL,
        ?CustomerPhoneInput $phone = new CustomerPhoneInput(self::COUNTRY_CODE, PhoneNumbers::MX_NATIONAL_NUMBER),
        ?string $birthDate = self::BIRTH_DATE,
        ?string $notes = self::NOTES,
        ?CustomerAddressSnapshot $address = new CustomerAddressSnapshot(
            self::STREET,
            self::CITY,
            self::STATE_ID,
            self::POSTAL_CODE,
            self::COUNTRY_CODE,
        ),
    ): UpdateCustomerInput {
        return new UpdateCustomerInput(
            customerId: $customerId,
            name: $name,
            email: $email,
            phone: $phone,
            birthDate: $birthDate,
            notes: $notes,
            address: $address,
        );
    }
}
