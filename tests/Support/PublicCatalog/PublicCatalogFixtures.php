<?php

declare(strict_types=1);

namespace Tests\Support\PublicCatalog;

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\ValueObjects\PublicBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicBusinessProfile;
use App\Domains\PublicCatalog\ValueObjects\PublicContact;
use App\Domains\PublicCatalog\ValueObjects\PublicGalleryImage;
use App\Domains\PublicCatalog\ValueObjects\PublicLink;
use App\Domains\PublicCatalog\ValueObjects\PublicLocation;
use App\Domains\PublicCatalog\ValueObjects\PublicScheduleEntry;
use App\Domains\PublicCatalog\ValueObjects\PublicService;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;

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

    public static function scheduleEntry(
        int $weekday = 1,
        string $startsAt = '09:00',
        string $endsAt = '14:00',
    ): PublicScheduleEntry {
        return new PublicScheduleEntry(weekday: $weekday, startsAt: $startsAt, endsAt: $endsAt);
    }

    public static function service(
        string $id = self::SERVICE_ID,
        string $name = 'Corte de pelo',
        string $slug = 'corte-de-pelo',
        ?string $description = 'Incluye lavado.',
        int $durationMinutes = 45,
        string $price = '250.00',
        ?string $imageUrl = self::SERVICE_IMAGE_URL,
    ): PublicService {
        return new PublicService(
            id: $id,
            name: $name,
            slug: $slug,
            description: $description,
            durationMinutes: $durationMinutes,
            price: $price,
            imageUrl: $imageUrl,
        );
    }

    public static function teamMember(
        string $id = self::TEAM_MEMBER_ID,
        string $name = 'Ada Lovelace',
    ): PublicTeamMember {
        return new PublicTeamMember(id: $id, name: $name);
    }

    public static function location(
        string $street = 'Avenida Insurgentes Sur 1602',
        string $city = 'Ciudad de México',
        ?string $state = 'Ciudad de México',
        string $postalCode = '03940',
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
        ?array $services = null,
        ?array $team = null,
        ?PublicLocation $location = null,
        ?PublicContact $contact = null,
    ): PublicBusinessPageData {
        return new PublicBusinessPageData(
            profile: $profile ?? self::profile(),
            brand: $brand ?? self::brand(),
            schedule: $schedule ?? [self::scheduleEntry()],
            services: $services ?? [self::service()],
            team: $team ?? [self::teamMember()],
            location: $location ?? self::location(),
            contact: $contact ?? self::contact(),
        );
    }

    public static function emptyPage(): PublicBusinessPageData
    {
        return new PublicBusinessPageData(
            profile: self::profile(about: null, logoUrl: null),
            brand: self::brand(bannerUrl: null, gallery: []),
            schedule: [],
            services: [],
            team: [],
            location: null,
            contact: self::contact(phone: null, links: []),
        );
    }
}
