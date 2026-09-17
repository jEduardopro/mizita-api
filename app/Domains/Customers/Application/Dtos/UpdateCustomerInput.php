<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\ValueObjects\BirthDate;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use DateTimeImmutable;

final readonly class UpdateCustomerInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $customerId,
        public string $name,
        public ?string $email,
        public ?CustomerPhoneInput $phone,
        public ?string $birthDate,
        public ?string $notes,
        public ?CustomerAddressSnapshot $address,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $customerId): self
    {
        return new self(
            customerId: $customerId,
            name: self::textOrEmpty($payload['name'] ?? null),
            email: self::textOrNull($payload['email'] ?? null),
            phone: CustomerPhoneInput::fromPayload($payload['phone'] ?? null),
            birthDate: self::textOrNull($payload['birth_date'] ?? null),
            notes: self::textOrNull($payload['notes'] ?? null),
            address: self::submittedAddress($payload['address'] ?? null),
        );
    }

    /**
     * @throws CustomerNotFound
     * @throws InvalidCustomerName
     * @throws InvalidCustomerEmail
     * @throws InvalidCustomerBirthDate
     * @throws InvalidCustomerNotes
     * @throws InvalidCustomerPhone
     */
    public function validate(): void
    {
        $this->validateCustomerId();
        $this->validateName();
        $this->validateEmail();
        $this->validateBirthDate();
        $this->validateNotes();
        $this->phone?->validate();
    }

    /**
     * @throws InvalidCustomerBirthDate
     */
    public function toBirthDate(): ?DateTimeImmutable
    {
        return BirthDate::fromNullable($this->birthDate);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function submittedAddress(mixed $section): ?CustomerAddressSnapshot
    {
        if (! is_array($section) || $section === []) {
            return null;
        }

        return new CustomerAddressSnapshot(
            street: self::textOrEmpty($section['street'] ?? null),
            city: self::textOrNull($section['city'] ?? null),
            stateId: self::textOrNull($section['state_id'] ?? null),
            postalCode: self::textOrNull($section['postal_code'] ?? null),
            countryCode: self::textOrEmpty($section['country_code'] ?? null),
        );
    }

    private function validateCustomerId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->customerId) !== 1) {
            throw CustomerNotFound::withId($this->customerId);
        }
    }

    private function validateName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            throw InvalidCustomerName::empty();
        }

        if (mb_strlen($name) > Customer::MAXIMUM_NAME_LENGTH) {
            throw InvalidCustomerName::tooLong(Customer::MAXIMUM_NAME_LENGTH);
        }
    }

    private function validateEmail(): void
    {
        CustomerEmail::fromNullable($this->email);
    }

    private function validateBirthDate(): void
    {
        $this->toBirthDate();
    }

    private function validateNotes(): void
    {
        if ($this->notes !== null && mb_strlen(trim($this->notes)) > Customer::MAXIMUM_NOTES_LENGTH) {
            throw InvalidCustomerNotes::tooLong(Customer::MAXIMUM_NOTES_LENGTH);
        }
    }
}
