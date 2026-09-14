<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;

final readonly class CreateCustomerInput
{
    private const MAXIMUM_NAME_LENGTH = 255;

    private const MAXIMUM_EMAIL_LENGTH = 254;

    private const MAXIMUM_PHONE_LENGTH = 255;

    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidCustomerEmail
     * @throws InvalidCustomerPhone
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            name: self::textOrEmpty($payload['name'] ?? null),
            email: self::emailOrNull($payload['email'] ?? null),
            phone: self::phoneOrNull($payload['phone'] ?? null),
        );
    }

    /**
     * @throws InvalidCustomerName
     * @throws InvalidCustomerEmail
     * @throws InvalidCustomerPhone
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateEmail();
        $this->validatePhone();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function emailOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidCustomerEmail::malformed();
        }

        return $value;
    }

    private static function phoneOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidCustomerPhone::malformed();
        }

        return $value;
    }

    private function validateName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            throw InvalidCustomerName::empty();
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidCustomerName::tooLong();
        }
    }

    private function validateEmail(): void
    {
        if ($this->email === null) {
            return;
        }

        if (mb_strlen($this->email) > self::MAXIMUM_EMAIL_LENGTH) {
            throw InvalidCustomerEmail::tooLong();
        }

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidCustomerEmail::malformed();
        }
    }

    private function validatePhone(): void
    {
        if ($this->phone === null) {
            return;
        }

        if (mb_strlen($this->phone) > self::MAXIMUM_PHONE_LENGTH) {
            throw InvalidCustomerPhone::tooLong();
        }
    }
}
