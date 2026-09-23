<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\IncompleteContactFields;
use App\Domains\Businesses\Exceptions\InvalidContactFieldRequirement;
use App\Domains\Businesses\ValueObjects\ContactFieldPreference;

final readonly class ContactFieldsInput
{
    public const PHONE_KEY = 'phone';

    public const EMAIL_KEY = 'email';

    public const ADDRESS_KEY = 'address';

    private const REQUIRED_KEYS = [
        self::PHONE_KEY,
        self::EMAIL_KEY,
        self::ADDRESS_KEY,
    ];

    /**
     * @param  list<string>  $absentKeys
     */
    public function __construct(
        public string $phone,
        public string $email,
        public string $address,
        private array $absentKeys = [],
    ) {}

    public static function fromPayload(mixed $payload): ?self
    {
        if (! is_array($payload)) {
            return null;
        }

        return new self(
            phone: self::textOrEmpty($payload[self::PHONE_KEY] ?? null),
            email: self::textOrEmpty($payload[self::EMAIL_KEY] ?? null),
            address: self::textOrEmpty($payload[self::ADDRESS_KEY] ?? null),
            absentKeys: self::absentKeysIn($payload),
        );
    }

    /**
     * @throws IncompleteContactFields
     * @throws InvalidContactFieldRequirement
     */
    public function validate(): void
    {
        $this->validatePresence();
        $this->validatePhone();
        $this->validateEmail();
        $this->validateAddress();
    }

    /**
     * @throws IncompleteContactFields
     */
    private function validatePresence(): void
    {
        if ($this->absentKeys === []) {
            return;
        }

        throw IncompleteContactFields::missing($this->absentKeys);
    }

    /**
     * @throws InvalidContactFieldRequirement
     */
    private function validatePhone(): void
    {
        self::assertKnownPreference(self::PHONE_KEY, $this->phone);
    }

    /**
     * @throws InvalidContactFieldRequirement
     */
    private function validateEmail(): void
    {
        self::assertKnownPreference(self::EMAIL_KEY, $this->email);
    }

    /**
     * @throws InvalidContactFieldRequirement
     */
    private function validateAddress(): void
    {
        self::assertKnownPreference(self::ADDRESS_KEY, $this->address);
    }

    /**
     * @throws InvalidContactFieldRequirement
     */
    private static function assertKnownPreference(string $field, string $value): void
    {
        if (ContactFieldPreference::tryFrom($value) !== null) {
            return;
        }

        throw InvalidContactFieldRequirement::forField($field, $value);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<string>
     */
    private static function absentKeysIn(array $payload): array
    {
        return array_values(array_filter(
            self::REQUIRED_KEYS,
            static fn (string $key): bool => ! array_key_exists($key, $payload),
        ));
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
