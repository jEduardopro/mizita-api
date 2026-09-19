<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

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
    ) {}
}
