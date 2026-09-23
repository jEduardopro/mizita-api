<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerAddress;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\ValueObjects\CustomerEmail;

final readonly class GuestContactInput
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?CustomerPhoneInput $phone,
        public ?string $notes,
        public ?GuestAddressInput $address = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            name: self::textOrEmpty($payload['name'] ?? null),
            email: self::textOrNull($payload['email'] ?? null),
            phone: CustomerPhoneInput::fromPayload($payload['phone'] ?? null),
            notes: self::textOrNull($payload['notes'] ?? null),
            address: GuestAddressInput::fromPayload($payload['address'] ?? null),
        );
    }

    /**
     * @throws InvalidCustomerName
     * @throws InvalidCustomerEmail
     * @throws InvalidCustomerPhone
     * @throws InvalidCustomerNotes
     * @throws InvalidCustomerAddress
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateEmail();
        $this->phone?->validate();
        $this->validateNotes();
        $this->address?->validate();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
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

    private function validateNotes(): void
    {
        if ($this->notes !== null && mb_strlen(trim($this->notes)) > Customer::MAXIMUM_NOTES_LENGTH) {
            throw InvalidCustomerNotes::tooLong(Customer::MAXIMUM_NOTES_LENGTH);
        }
    }
}
