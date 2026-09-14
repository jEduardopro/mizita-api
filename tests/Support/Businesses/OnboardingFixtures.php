<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeImmutable;
use Tests\Support\PhoneNumbers;

/**
 * The fixed cast of a business signup, shared by the entity and use case tests.
 *
 * Everything here is a literal a test can name in an assertion: one name, the
 * address it folds to, one instant, one generated id. A builder rather than a
 * data provider, so each test overrides only the field it is about.
 */
final class OnboardingFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    /** The only id the generator hands out on the happy path: the business. */
    public const GENERATED_BUSINESS_ID = '01930000-0000-7000-8000-000000000001';

    public const OWNER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const INDUSTRY_ID = '01930000-0000-7000-8000-0000000000f1';

    /** Accented and with a tilde, because the address it folds to is the point. */
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

    /**
     * A business as the use case builds one: created, never restored.
     */
    public static function business(
        string $id = self::GENERATED_BUSINESS_ID,
        string $name = self::NAME,
        string $slug = self::SLUG,
        string $timezone = self::TIMEZONE,
        string $industryId = self::INDUSTRY_ID,
    ): Business {
        return Business::create(
            id: $id,
            name: $name,
            slug: Slug::restore($slug),
            industryId: $industryId,
            timezone: Timezone::restore($timezone),
            now: self::now(),
        );
    }

    /**
     * What the owner typed into the form: a country string and some digits,
     * neither of them judged yet.
     *
     * Its defaults are the same pair phone() below is built from, so a test that
     * passes this to the use case and that to FakePhoneNumberParser::accepting()
     * gets a number the parser recognises, with no literal repeated between them.
     */
    public static function submittedPhone(
        CountryCode $country = CountryCode::Mx,
        string $nationalNumber = PhoneNumbers::MX_NATIONAL_NUMBER,
    ): PhoneNumberInput {
        return new PhoneNumberInput($country->value, $nationalNumber);
    }

    /**
     * The same number once a parser has established it is real, which is what the
     * phone book is handed.
     *
     * A number carries six facts a parser established, none of which this domain
     * reads, so the fixture defers to the shared builder rather than restating
     * them. Pass a country or some digits only when the test is about the number
     * itself.
     */
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
