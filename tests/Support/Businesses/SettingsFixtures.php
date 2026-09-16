<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Application\Dtos\AppearanceInput;
use App\Domains\Businesses\Application\Dtos\AttachBusinessLogoInput;
use App\Domains\Businesses\Application\Dtos\BrandDetailsInput;
use App\Domains\Businesses\Application\Dtos\ContactInput;
use App\Domains\Businesses\Application\Dtos\LinksInput;
use App\Domains\Businesses\Application\Dtos\LocationInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Application\Dtos\ScheduleInput;
use App\Domains\Businesses\Application\Dtos\UpdateBusinessSettingsInput;
use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\PhoneNumbers;

final class SettingsFixtures
{
    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const OTHER_INDUSTRY_ID = '01930000-0000-7000-8000-0000000000f2';

    public const STATE_ID = '01930000-0000-7000-8000-0000000000e1';

    public const LOGO_URL = 'https://mizita.test/media/1/logo.png';

    public const BANNER_URL = 'https://mizita.test/media/2/banner.png';

    public const CONTACT_EMAIL = 'hola@barberia.com';

    public const ABOUT = 'Barbería clásica desde 2019.';

    public const STREET = 'Avenida Insurgentes Sur 1602';

    public const CITY = 'Ciudad de México';

    public const POSTAL_CODE = '03940';

    public const LATITUDE = '19.3627888';

    public const LONGITUDE = '-99.1768069';

    public const SOURCE_PATH = '/tmp/php-upload-logo';

    public const FILE_NAME = 'logo.png';

    public static function brand(
        string $name = OnboardingFixtures::NAME,
        string $slug = OnboardingFixtures::SLUG,
        string $industryId = OnboardingFixtures::INDUSTRY_ID,
        ?string $about = self::ABOUT,
    ): BrandDetailsInput {
        return new BrandDetailsInput($name, $slug, $industryId, $about);
    }

    public static function appearance(
        string $accentColor = 'teal',
        string $buttonShape = 'rounded',
        string $theme = 'dark',
    ): AppearanceInput {
        return new AppearanceInput($accentColor, $buttonShape, $theme);
    }

    public static function contact(
        ?string $contactEmail = self::CONTACT_EMAIL,
        ?PhoneNumberInput $phone = null,
    ): ContactInput {
        return new ContactInput($contactEmail, $phone);
    }

    public static function submittedPhone(
        CountryCode $country = CountryCode::Mx,
        string $nationalNumber = PhoneNumbers::MX_NATIONAL_NUMBER,
    ): PhoneNumberInput {
        return new PhoneNumberInput($country->value, $nationalNumber);
    }

    public static function location(
        string $street = self::STREET,
        string $city = self::CITY,
        ?string $stateId = self::STATE_ID,
        string $postalCode = self::POSTAL_CODE,
        string $countryCode = 'MX',
        ?string $latitude = self::LATITUDE,
        ?string $longitude = self::LONGITUDE,
        string $currencyCode = 'MXN',
        string $timezone = OnboardingFixtures::TIMEZONE,
    ): LocationInput {
        return new LocationInput(
            street: $street,
            city: $city,
            stateId: $stateId,
            postalCode: $postalCode,
            countryCode: $countryCode,
            latitude: $latitude,
            longitude: $longitude,
            currencyCode: $currencyCode,
            timezone: $timezone,
        );
    }

    public static function schedule(BusinessScheduleEntry ...$entries): ScheduleInput
    {
        return new ScheduleInput($entries === [] ? [self::entry()] : array_values($entries));
    }

    public static function entry(
        int $weekday = 1,
        string $startsAt = '09:00',
        string $endsAt = '14:00',
    ): BusinessScheduleEntry {
        return new BusinessScheduleEntry($weekday, $startsAt, $endsAt);
    }

    public static function links(BusinessLinkSnapshot ...$links): LinksInput
    {
        return new LinksInput($links === [] ? [self::link()] : array_values($links));
    }

    public static function link(
        string $platform = 'instagram',
        string $url = 'https://instagram.com/barberia',
        int $position = 0,
    ): BusinessLinkSnapshot {
        return new BusinessLinkSnapshot($platform, $url, $position);
    }

    public static function everything(): UpdateBusinessSettingsInput
    {
        return new UpdateBusinessSettingsInput(
            brand: self::brand(),
            appearance: self::appearance(),
            contact: self::contact(phone: self::submittedPhone()),
            location: self::location(),
            schedule: self::schedule(),
            links: self::links(),
        );
    }

    public static function address(
        string $street = self::STREET,
        string $city = self::CITY,
        ?string $stateId = self::STATE_ID,
        string $postalCode = self::POSTAL_CODE,
        string $countryCode = 'MX',
        ?string $latitude = self::LATITUDE,
        ?string $longitude = self::LONGITUDE,
    ): BusinessAddressSnapshot {
        return new BusinessAddressSnapshot(
            street: $street,
            city: $city,
            stateId: $stateId,
            postalCode: $postalCode,
            countryCode: $countryCode,
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    /**
     * @param  list<object>  $gallery
     */
    public static function bookingPage(
        string $accentColor = 'ink',
        string $buttonShape = 'pill',
        string $theme = 'light',
        ?string $bannerUrl = null,
        array $gallery = [],
    ): BookingPageSnapshot {
        return new BookingPageSnapshot($accentColor, $buttonShape, $theme, $bannerUrl, $gallery);
    }

    public static function logo(
        string $sourcePath = self::SOURCE_PATH,
        string $fileName = self::FILE_NAME,
        string $mimeType = 'image/png',
        int $sizeInBytes = 1024,
    ): AttachBusinessLogoInput {
        return new AttachBusinessLogoInput($sourcePath, $fileName, $mimeType, $sizeInBytes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        return [
            'brand' => [
                'name' => OnboardingFixtures::NAME,
                'slug' => OnboardingFixtures::SLUG,
                'industry_id' => OnboardingFixtures::INDUSTRY_ID,
                'about' => self::ABOUT,
            ],
            'appearance' => [
                'accent_color' => 'teal',
                'button_shape' => 'rounded',
                'theme' => 'dark',
            ],
            'contact' => [
                'contact_email' => self::CONTACT_EMAIL,
                'phone' => [
                    'country_code' => 'MX',
                    'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
                ],
            ],
            'location' => [
                'street' => self::STREET,
                'city' => self::CITY,
                'state_id' => self::STATE_ID,
                'postal_code' => self::POSTAL_CODE,
                'country_code' => 'MX',
                'latitude' => self::LATITUDE,
                'longitude' => self::LONGITUDE,
                'currency_code' => 'MXN',
                'timezone' => OnboardingFixtures::TIMEZONE,
            ],
            'schedule' => [
                ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
            ],
            'links' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/barberia'],
            ],
            ...$overrides,
        ];
    }
}
