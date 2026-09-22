<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\CurrencyCode;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeImmutable;
use Tests\Support\PhoneNumbers;

final class OnboardingFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const GENERATED_BUSINESS_ID = '01930000-0000-7000-8000-000000000001';

    public const OWNER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const INDUSTRY_ID = '01930000-0000-7000-8000-0000000000f1';

    public const NAME = 'Barbería Ñandú';

    public const SLUG = 'barberia-nandu';

    public const TIMEZONE = 'Europe/Madrid';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function input(
        string $name = self::NAME,
        string $timezone = self::TIMEZONE,
        string $industryId = self::INDUSTRY_ID,
        ?PhoneNumberInput $phone = null,
        string $ownerAccountId = self::OWNER_ACCOUNT_ID,
    ): OnboardBusinessInput {
        return new OnboardBusinessInput(
            ownerAccountId: $ownerAccountId,
            name: $name,
            timezone: $timezone,
            industryId: $industryId,
            phone: $phone,
        );
    }

    public static function business(
        string $id = self::GENERATED_BUSINESS_ID,
        string $name = self::NAME,
        string $slug = self::SLUG,
        string $timezone = self::TIMEZONE,
        string $industryId = self::INDUSTRY_ID,
        ?ContactEmail $contactEmail = null,
        ?About $about = null,
        ?CurrencyCode $currency = null,
    ): Business {
        return Business::create(
            id: $id,
            name: $name,
            slug: Slug::restore($slug),
            industryId: $industryId,
            timezone: Timezone::restore($timezone),
            now: self::now(),
            contactEmail: $contactEmail,
            about: $about,
            currency: $currency,
        );
    }

    public static function submittedPhone(
        CountryCode $country = CountryCode::Mx,
        string $nationalNumber = PhoneNumbers::MX_NATIONAL_NUMBER,
    ): PhoneNumberInput {
        return new PhoneNumberInput($country->value, $nationalNumber);
    }

    public static function phone(
        CountryCode $country = CountryCode::Mx,
        string $nationalNumber = PhoneNumbers::MX_NATIONAL_NUMBER,
    ): PhoneNumber {
        return match ($country) {
            CountryCode::Mx => PhoneNumbers::mexican($nationalNumber),
            CountryCode::Us => PhoneNumbers::american($nationalNumber),
        };
    }
}
