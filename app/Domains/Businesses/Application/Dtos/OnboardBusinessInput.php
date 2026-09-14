<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Exceptions\InvalidBusinessOwner;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Domains\Businesses\ValueObjects\Timezone;

final readonly class OnboardBusinessInput
{
    private const MINIMUM_NAME_LENGTH = 2;

    private const MAXIMUM_NAME_LENGTH = 120;

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $ownerAccountId,
        public string $name,
        public string $timezone,
        public string $industryId,
        public ?PhoneNumberInput $phone = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $ownerAccountId): self
    {
        return new self(
            ownerAccountId: $ownerAccountId,
            name: self::textOrEmpty($payload['name'] ?? null),
            timezone: self::textOrEmpty($payload['timezone'] ?? null),
            industryId: self::textOrEmpty($payload['industry_id'] ?? null),
            phone: self::submittedPhone($payload),
        );
    }

    /**
     * @throws InvalidBusinessOwner
     * @throws InvalidBusinessName
     * @throws InvalidBusinessTimezone
     * @throws UnknownIndustry
     * @throws UnsupportedPhoneNumber
     */
    public function validate(): void
    {
        $this->validateOwnerAccountId();
        $this->validateName();
        $this->validateTimezone();
        $this->validateIndustryId();
        $this->phone?->validate();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function submittedPhone(array $payload): ?PhoneNumberInput
    {
        $phone = $payload['phone'] ?? null;

        if ($phone === null || $phone === []) {
            return null;
        }

        $parts = is_array($phone) ? $phone : [];

        return new PhoneNumberInput(
            countryCode: self::textOrEmpty($parts['country_code'] ?? null),
            nationalNumber: self::textOrEmpty($parts['national_number'] ?? null),
        );
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateOwnerAccountId(): void
    {
        if (trim($this->ownerAccountId) === '') {
            throw InvalidBusinessOwner::missing();
        }
    }

    private function validateName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            throw InvalidBusinessName::empty();
        }

        if (mb_strlen($name) < self::MINIMUM_NAME_LENGTH) {
            throw InvalidBusinessName::tooShort($name);
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidBusinessName::tooLong($name);
        }
    }

    private function validateTimezone(): void
    {
        Timezone::fromString($this->timezone);
    }

    private function validateIndustryId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->industryId) !== 1) {
            throw UnknownIndustry::withId($this->industryId);
        }
    }
}
