<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\InvalidBusinessAbout;
use App\Domains\Businesses\Exceptions\InvalidBusinessContactEmail;
use App\Domains\Businesses\Exceptions\InvalidBusinessCurrency;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Exceptions\InvalidBusinessSlug;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;

final readonly class UpdateBusinessSettingsInput
{
    public function __construct(
        public ?BrandDetailsInput $brand = null,
        public ?AppearanceInput $appearance = null,
        public ?ContactInput $contact = null,
        public ?LocationInput $location = null,
        public ?ScheduleInput $schedule = null,
        public ?LinksInput $links = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            brand: self::submittedBrand($payload['brand'] ?? null),
            appearance: self::submittedAppearance($payload['appearance'] ?? null),
            contact: self::submittedContact($payload['contact'] ?? null),
            location: self::submittedLocation($payload['location'] ?? null),
            schedule: self::submittedSchedule($payload['schedule'] ?? null),
            links: self::submittedLinks($payload['links'] ?? null),
        );
    }

    /**
     * @throws InvalidBusinessName
     * @throws InvalidBusinessSlug
     * @throws UnknownIndustry
     * @throws InvalidBusinessAbout
     * @throws InvalidBusinessContactEmail
     * @throws UnsupportedPhoneNumber
     * @throws InvalidBusinessCurrency
     * @throws InvalidBusinessTimezone
     */
    public function validate(): void
    {
        $this->brand?->validate();
        $this->contact?->validate();
        $this->location?->validate();
    }

    public function changesBusinessRecord(): bool
    {
        return $this->brand !== null
            || $this->contact !== null
            || $this->location !== null;
    }

    private static function submittedBrand(mixed $section): ?BrandDetailsInput
    {
        if (! is_array($section)) {
            return null;
        }

        return new BrandDetailsInput(
            name: self::textOrEmpty($section['name'] ?? null),
            slug: self::textOrEmpty($section['slug'] ?? null),
            industryId: self::textOrEmpty($section['industry_id'] ?? null),
            about: self::textOrNull($section['about'] ?? null),
        );
    }

    private static function submittedAppearance(mixed $section): ?AppearanceInput
    {
        if (! is_array($section)) {
            return null;
        }

        return new AppearanceInput(
            accentColor: self::textOrEmpty($section['accent_color'] ?? null),
            buttonShape: self::textOrEmpty($section['button_shape'] ?? null),
            theme: self::textOrEmpty($section['theme'] ?? null),
        );
    }

    private static function submittedContact(mixed $section): ?ContactInput
    {
        if (! is_array($section)) {
            return null;
        }

        return new ContactInput(
            contactEmail: self::textOrNull($section['contact_email'] ?? null),
            phone: self::submittedPhone($section['phone'] ?? null),
        );
    }

    private static function submittedPhone(mixed $phone): ?PhoneNumberInput
    {
        if (! is_array($phone) || $phone === []) {
            return null;
        }

        return new PhoneNumberInput(
            countryCode: self::textOrEmpty($phone['country_code'] ?? null),
            nationalNumber: self::textOrEmpty($phone['national_number'] ?? null),
        );
    }

    private static function submittedLocation(mixed $section): ?LocationInput
    {
        if (! is_array($section)) {
            return null;
        }

        return new LocationInput(
            street: self::textOrEmpty($section['street'] ?? null),
            city: self::textOrEmpty($section['city'] ?? null),
            stateId: self::textOrNull($section['state_id'] ?? null),
            postalCode: self::textOrEmpty($section['postal_code'] ?? null),
            countryCode: self::textOrEmpty($section['country_code'] ?? null),
            latitude: self::textOrNull($section['latitude'] ?? null),
            longitude: self::textOrNull($section['longitude'] ?? null),
            currencyCode: self::textOrEmpty($section['currency_code'] ?? null),
            timezone: self::textOrEmpty($section['timezone'] ?? null),
        );
    }

    private static function submittedSchedule(mixed $section): ?ScheduleInput
    {
        if (! is_array($section)) {
            return null;
        }

        $entries = [];

        foreach ($section as $entry) {
            $parts = is_array($entry) ? $entry : [];

            $entries[] = new BusinessScheduleEntry(
                weekday: self::numberOrZero($parts['weekday'] ?? null),
                startsAt: self::textOrEmpty($parts['starts_at'] ?? null),
                endsAt: self::textOrEmpty($parts['ends_at'] ?? null),
            );
        }

        return new ScheduleInput($entries);
    }

    private static function submittedLinks(mixed $section): ?LinksInput
    {
        if (! is_array($section)) {
            return null;
        }

        $links = [];
        $position = 0;

        foreach ($section as $link) {
            $parts = is_array($link) ? $link : [];

            $links[] = new BusinessLinkSnapshot(
                platform: self::textOrEmpty($parts['platform'] ?? null),
                url: self::textOrEmpty($parts['url'] ?? null),
                position: $position,
            );

            $position++;
        }

        return new LinksInput($links);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function numberOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
