<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\Exceptions\InvalidGuestEmail;
use App\Domains\Appointments\Exceptions\InvalidGuestName;
use App\Domains\Appointments\Exceptions\InvalidGuestPhone;
use App\Domains\Appointments\ValueObjects\GuestContact;
use App\Domains\Appointments\ValueObjects\GuestPhone;

final readonly class GuestDetailsInput
{
    public const MAXIMUM_NAME_LENGTH = 120;

    public const MAXIMUM_EMAIL_LENGTH = 254;

    public const COUNTRY_CODE_LENGTH = 2;

    public const MAXIMUM_NATIONAL_NUMBER_LENGTH = 24;

    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phoneCountryCode,
        public ?string $phoneNationalNumber,
        public ?GuestAddressInput $address = null,
    ) {}

    public static function fromPayload(mixed $payload): self
    {
        $details = is_array($payload) ? $payload : [];
        $phone = is_array($details['phone'] ?? null) ? $details['phone'] : [];

        return new self(
            name: self::textOrEmpty($details['name'] ?? null),
            email: self::textOrNull($details['email'] ?? null),
            phoneCountryCode: self::textOrNull($phone['country_code'] ?? null),
            phoneNationalNumber: self::textOrNull($phone['national_number'] ?? null),
            address: GuestAddressInput::fromPayload($details['address'] ?? null),
        );
    }

    /**
     * @throws InvalidGuestName
     * @throws InvalidGuestEmail
     * @throws InvalidGuestPhone
     * @throws InvalidGuestAddress
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateEmail();
        $this->validatePhone();
        $this->address?->validate();
    }

    public function toContact(): GuestContact
    {
        return new GuestContact(
            name: trim($this->name),
            email: $this->email,
            phone: $this->toPhone(),
            address: $this->address?->toAddress(),
        );
    }

    private function toPhone(): ?GuestPhone
    {
        if ($this->phoneCountryCode === null || $this->phoneNationalNumber === null) {
            return null;
        }

        return new GuestPhone(
            countryCode: trim($this->phoneCountryCode),
            nationalNumber: trim($this->phoneNationalNumber),
        );
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
            throw InvalidGuestName::empty();
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidGuestName::tooLong(self::MAXIMUM_NAME_LENGTH);
        }
    }

    private function validateEmail(): void
    {
        if ($this->email === null) {
            return;
        }

        $email = trim($this->email);

        if (mb_strlen($email) > self::MAXIMUM_EMAIL_LENGTH) {
            throw InvalidGuestEmail::tooLong(self::MAXIMUM_EMAIL_LENGTH);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidGuestEmail::malformed();
        }
    }

    private function validatePhone(): void
    {
        if ($this->phoneCountryCode === null && $this->phoneNationalNumber === null) {
            return;
        }

        if ($this->phoneCountryCode === null || $this->phoneNationalNumber === null) {
            throw InvalidGuestPhone::malformed();
        }

        if (mb_strlen(trim($this->phoneCountryCode)) !== self::COUNTRY_CODE_LENGTH) {
            throw InvalidGuestPhone::malformed();
        }

        if (mb_strlen(trim($this->phoneNationalNumber)) > self::MAXIMUM_NATIONAL_NUMBER_LENGTH) {
            throw InvalidGuestPhone::malformed();
        }
    }
}
