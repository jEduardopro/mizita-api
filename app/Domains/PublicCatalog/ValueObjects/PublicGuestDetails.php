<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;

final readonly class PublicGuestDetails
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
        public ?PublicGuestAddress $address = null,
    ) {}

    /**
     * @throws InvalidPublicGuestAddress
     */
    public function validate(): void
    {
        $this->address?->validate();
    }

    public function hasPhone(): bool
    {
        return $this->phoneCountryCode !== null && $this->phoneNationalNumber !== null;
    }

    public function hasEmail(): bool
    {
        return $this->email !== null;
    }

    public function hasCompleteAddress(): bool
    {
        return $this->address?->isComplete() ?? false;
    }

    public function hasBlankAddress(): bool
    {
        return $this->address?->isBlank() ?? true;
    }

    public function withoutPhone(): self
    {
        return new self(
            name: $this->name,
            email: $this->email,
            phoneCountryCode: null,
            phoneNationalNumber: null,
            address: $this->address,
        );
    }

    public function withoutEmail(): self
    {
        return new self(
            name: $this->name,
            email: null,
            phoneCountryCode: $this->phoneCountryCode,
            phoneNationalNumber: $this->phoneNationalNumber,
            address: $this->address,
        );
    }

    public function withoutAddress(): self
    {
        return new self(
            name: $this->name,
            email: $this->email,
            phoneCountryCode: $this->phoneCountryCode,
            phoneNationalNumber: $this->phoneNationalNumber,
            address: null,
        );
    }
}
