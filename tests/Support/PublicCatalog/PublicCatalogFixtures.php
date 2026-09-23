<?php

declare(strict_types=1);

namespace Tests\Support\PublicCatalog;

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;
use App\Domains\PublicCatalog\ValueObjects\PublicAvailableDay;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingPolicy;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingRequest;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingStatus;
use App\Domains\PublicCatalog\ValueObjects\PublicBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicBusinessProfile;
use App\Domains\PublicCatalog\ValueObjects\PublicContact;
use App\Domains\PublicCatalog\ValueObjects\PublicGalleryImage;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestDetails;
use App\Domains\PublicCatalog\ValueObjects\PublicLink;
use App\Domains\PublicCatalog\ValueObjects\PublicLocation;
use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;
use App\Domains\PublicCatalog\ValueObjects\PublicScheduleEntry;
use App\Domains\PublicCatalog\ValueObjects\PublicService;
use App\Domains\PublicCatalog\ValueObjects\PublicSlotQuery;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;
use DateTimeImmutable;

final class PublicCatalogFixtures
{
    public const BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const SERVICE_ID = '01930000-0000-7000-8000-0000000000e1';

    public const SECOND_SERVICE_ID = '01930000-0000-7000-8000-0000000000e2';

    public const TEAM_MEMBER_ID = '01930000-0000-7000-8000-0000000000d1';

    public const SECOND_TEAM_MEMBER_ID = '01930000-0000-7000-8000-0000000000d2';

    public const IMAGE_ID = '01930000-0000-7000-8000-0000000000c1';

    public const SECOND_IMAGE_ID = '01930000-0000-7000-8000-0000000000c2';

    public const SLUG = 'ada-salon';

    public const UNKNOWN_SLUG = 'nobody-here';

    public const NAME = 'Ada Salón';

    public const TIMEZONE = 'Europe/Madrid';

    public const CURRENCY_CODE = 'MXN';

    public const LOGO_URL = 'https://cdn.mizita.test/businesses/logo.png';

    public const BANNER_URL = 'https://cdn.mizita.test/booking-pages/banner.jpg';

    public const GALLERY_URL = 'https://cdn.mizita.test/booking-pages/one.jpg';

    public const SERVICE_IMAGE_URL = 'https://cdn.mizita.test/services/corte.jpg';

    public const INSTAGRAM_URL = 'https://instagram.com/ada.salon';

    public const POLICY_MESSAGE = 'Cancela con 24 horas de anticipación.';

    public const REFERENCE_CODE = 'A2B3C4D5';

    public const MANAGE_TOKEN = 'a1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9f';

    public const STARTS_AT = '2026-03-10T09:00:00+00:00';

    public const ENDS_AT = '2026-03-10T09:45:00+00:00';

    public const GUEST_NAME = 'Ada Lovelace';

    public const GUEST_EMAIL = 'ada@example.com';

    public const GUEST_PHONE_COUNTRY_CODE = 'MX';

    public const GUEST_PHONE_NATIONAL_NUMBER = '5512345678';

    public const GUEST_STREET = 'Av. Reforma 123';

    public const GUEST_CITY = 'Monterrey';

    public const GUEST_STATE = 'Nuevo León';

    public const GUEST_POSTAL_CODE = '64000';

    public const CLOSES_AT = '18:00';

    public const OPENS_AT = '09:00';

    public const OPENS_ON_WEEKDAY = 1;

    public const LAST_BOOKABLE_DATE = '2027-03-10';

    public static function slotQuery(
        string $serviceId = self::SERVICE_ID,
        string $staffId = self::TEAM_MEMBER_ID,
        string $from = '2026-03-10',
        string $to = '2026-03-12',
    ): PublicSlotQuery {
        return new PublicSlotQuery(serviceId: $serviceId, staffId: $staffId, from: $from, to: $to);
    }

    /**
     * @param  list<string>  $starts
     */
    public static function availableDay(string $date = '2026-03-10', array $starts = [self::STARTS_AT]): PublicAvailableDay
    {
        return new PublicAvailableDay(
            date: $date,
            starts: array_map(static fn (string $start): DateTimeImmutable => new DateTimeImmutable($start), $starts),
        );
    }

    public static function guestDetails(
        string $name = self::GUEST_NAME,
        ?string $email = self::GUEST_EMAIL,
        ?string $phoneCountryCode = self::GUEST_PHONE_COUNTRY_CODE,
        ?string $phoneNationalNumber = self::GUEST_PHONE_NATIONAL_NUMBER,
        ?PublicGuestAddress $address = null,
    ): PublicGuestDetails {
        return new PublicGuestDetails(
            name: $name,
            email: $email,
            phoneCountryCode: $phoneCountryCode,
            phoneNationalNumber: $phoneNationalNumber,
            address: $address,
        );
    }

    public static function guestAddress(
        ?string $street = self::GUEST_STREET,
        ?string $city = self::GUEST_CITY,
        ?string $state = self::GUEST_STATE,
        ?string $postalCode = self::GUEST_POSTAL_CODE,
    ): PublicGuestAddress {
        return new PublicGuestAddress(street: $street, city: $city, state: $state, postalCode: $postalCode);
    }

    public static function contactFields(
        GuestFieldRequirement $phone = GuestFieldRequirement::Required,
        GuestFieldRequirement $email = GuestFieldRequirement::Optional,
        GuestFieldRequirement $address = GuestFieldRequirement::Hidden,
    ): GuestFormFields {
        return new GuestFormFields(phone: $phone, email: $email, address: $address);
    }

    public static function bookingRequest(
        string $serviceId = self::SERVICE_ID,
        string $staffMemberId = self::TEAM_MEMBER_ID,
        string $startsAt = self::STARTS_AT,
        ?PublicGuestDetails $guest = null,
        ?string $notes = null,
    ): PublicBookingRequest {
        return new PublicBookingRequest(
            serviceId: $serviceId,
            staffMemberId: $staffMemberId,
            startsAt: $startsAt,
            guest: $guest ?? self::guestDetails(),
            notes: $notes,
        );
    }

    public static function bookingCredentials(
        string $referenceCode = self::REFERENCE_CODE,
        string $manageToken = self::MANAGE_TOKEN,
    ): PublicBookingCredentials {
        return new PublicBookingCredentials(referenceCode: $referenceCode, manageToken: $manageToken);
    }

    public static function guestBooking(
        string $referenceCode = self::REFERENCE_CODE,
        string $customerName = self::GUEST_NAME,
        PublicBookingStatus $status = PublicBookingStatus::Booked,
        ?string $cancelledAt = null,
        ?int $cancellationWindowMinutes = 120,
        bool $changeable = true,
    ): PublicGuestBooking {
        return new PublicGuestBooking(
            referenceCode: $referenceCode,
            customerName: $customerName,
            serviceName: 'Corte de pelo',
            staffMemberName: 'Ada Lovelace',
            startsAt: new DateTimeImmutable(self::STARTS_AT),
            endsAt: new DateTimeImmutable(self::ENDS_AT),
            durationMinutes: 45,
            status: $status,
            cancelledAt: $cancelledAt === null ? null : new DateTimeImmutable($cancelledAt),
            cancellationWindowMinutes: $cancellationWindowMinutes,
            changeable: $changeable,
        );
    }

    public static function bookingConfirmation(
        ?PublicGuestBooking $booking = null,
        string $manageToken = self::MANAGE_TOKEN,
    ): PublicGuestBookingConfirmation {
        return new PublicGuestBookingConfirmation(
            booking: $booking ?? self::guestBooking(),
            manageToken: $manageToken,
        );
    }

    public static function profile(
        string $id = self::BUSINESS_ID,
        string $name = self::NAME,
        string $slug = self::SLUG,
        ?string $about = 'Cortes y color desde 2019.',
        string $timezone = self::TIMEZONE,
        string $currencyCode = self::CURRENCY_CODE,
        ?string $logoUrl = self::LOGO_URL,
    ): PublicBusinessProfile {
        return new PublicBusinessProfile(
            id: $id,
            name: $name,
            slug: $slug,
            about: $about,
            timezone: $timezone,
            currencyCode: $currencyCode,
            logoUrl: $logoUrl,
        );
    }

    /**
     * @param  list<PublicGalleryImage>|null  $gallery
     */
    public static function brand(
        string $accentColor = 'teal',
        string $buttonShape = 'rounded',
        string $theme = 'dark',
        ?string $bannerUrl = self::BANNER_URL,
        ?array $gallery = null,
    ): PublicBrand {
        return new PublicBrand(
            accentColor: $accentColor,
            buttonShape: $buttonShape,
            theme: $theme,
            bannerUrl: $bannerUrl,
            gallery: $gallery ?? [self::galleryImage()],
        );
    }

    public static function galleryImage(
        string $id = self::IMAGE_ID,
        string $url = self::GALLERY_URL,
    ): PublicGalleryImage {
        return new PublicGalleryImage(id: $id, url: $url);
    }

    public static function openState(string $closesAt = self::CLOSES_AT): PublicOpenState
    {
        return PublicOpenState::openUntil($closesAt);
    }

    public static function closedState(
        int $opensOnWeekday = self::OPENS_ON_WEEKDAY,
        string $opensAt = self::OPENS_AT,
    ): PublicOpenState {
        return PublicOpenState::closedUntil($opensOnWeekday, $opensAt);
    }

    public static function scheduleEntry(
        int $weekday = 1,
        string $startsAt = '09:00',
        string $endsAt = '14:00',
    ): PublicScheduleEntry {
        return new PublicScheduleEntry(weekday: $weekday, startsAt: $startsAt, endsAt: $endsAt);
    }

    /**
     * @param  list<string>|null  $staffIds
     */
    public static function service(
        string $id = self::SERVICE_ID,
        string $name = 'Corte de pelo',
        string $slug = 'corte-de-pelo',
        ?string $description = 'Incluye lavado.',
        int $durationMinutes = 45,
        string $price = '250.00',
        ?string $imageUrl = self::SERVICE_IMAGE_URL,
        ?array $staffIds = null,
    ): PublicService {
        return new PublicService(
            id: $id,
            name: $name,
            slug: $slug,
            description: $description,
            durationMinutes: $durationMinutes,
            price: $price,
            imageUrl: $imageUrl,
            staffIds: $staffIds ?? [self::TEAM_MEMBER_ID],
        );
    }

    public static function bookingPolicy(string $policyMessage = self::POLICY_MESSAGE): PublicBookingPolicy
    {
        return new PublicBookingPolicy(policyMessage: $policyMessage);
    }

    public static function teamMember(
        string $id = self::TEAM_MEMBER_ID,
        string $name = 'Ada Lovelace',
    ): PublicTeamMember {
        return new PublicTeamMember(id: $id, name: $name);
    }

    public static function location(
        string $street = 'Avenida Insurgentes Sur 1602',
        ?string $city = 'Ciudad de México',
        ?string $state = 'Ciudad de México',
        ?string $postalCode = '03940',
        string $countryCode = 'MX',
        ?string $latitude = '19.3627888',
        ?string $longitude = '-99.1768069',
    ): PublicLocation {
        return new PublicLocation(
            street: $street,
            city: $city,
            state: $state,
            postalCode: $postalCode,
            countryCode: $countryCode,
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    /**
     * @param  list<PublicLink>|null  $links
     */
    public static function contact(
        ?string $phone = '+525512345678',
        ?array $links = null,
    ): PublicContact {
        return new PublicContact(
            phone: $phone,
            links: $links ?? [new PublicLink(platform: 'instagram', url: self::INSTAGRAM_URL)],
        );
    }

    /**
     * @param  list<PublicScheduleEntry>|null  $schedule
     * @param  list<PublicService>|null  $services
     * @param  list<PublicTeamMember>|null  $team
     */
    public static function page(
        ?PublicBusinessProfile $profile = null,
        ?PublicBrand $brand = null,
        ?array $schedule = null,
        ?PublicOpenState $openState = null,
        string $lastBookableDate = self::LAST_BOOKABLE_DATE,
        ?array $services = null,
        ?array $team = null,
        ?PublicLocation $location = null,
        ?PublicContact $contact = null,
        ?PublicBookingPolicy $bookingPolicy = new PublicBookingPolicy(self::POLICY_MESSAGE),
        ?GuestFormFields $contactFields = null,
    ): PublicBusinessPageData {
        return new PublicBusinessPageData(
            profile: $profile ?? self::profile(),
            brand: $brand ?? self::brand(),
            schedule: $schedule ?? [self::scheduleEntry()],
            openState: $openState ?? self::openState(),
            lastBookableDate: $lastBookableDate,
            services: $services ?? [self::service()],
            team: $team ?? [self::teamMember()],
            location: $location ?? self::location(),
            contact: $contact ?? self::contact(),
            bookingPolicy: $bookingPolicy,
            contactFields: $contactFields ?? self::contactFields(),
        );
    }

    public static function emptyPage(): PublicBusinessPageData
    {
        return new PublicBusinessPageData(
            profile: self::profile(about: null, logoUrl: null),
            brand: self::brand(bannerUrl: null, gallery: []),
            schedule: [],
            openState: PublicOpenState::closedIndefinitely(),
            lastBookableDate: self::LAST_BOOKABLE_DATE,
            services: [],
            team: [],
            location: null,
            contact: self::contact(phone: null, links: []),
            bookingPolicy: null,
            contactFields: self::contactFields(),
        );
    }
}
