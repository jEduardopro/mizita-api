<?php

declare(strict_types=1);

namespace Tests\Support\BookingPages;

use App\Domains\BookingPages\Application\Dtos\AttachBookingPageImageInput;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class BookingPageFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const PAGE_ID = '01930000-0000-7000-8000-0000000000a1';

    public const OTHER_PAGE_ID = '01930000-0000-7000-8000-0000000000a2';

    public const GENERATED_PAGE_ID = '01930000-0000-7000-8000-0000000000a9';

    public const IMAGE_ID = '01930000-0000-7000-8000-0000000000c1';

    public const SECOND_IMAGE_ID = '01930000-0000-7000-8000-0000000000c2';

    public const THIRD_IMAGE_ID = '01930000-0000-7000-8000-0000000000c3';

    public const NEIGHBOUR_IMAGE_ID = '01930000-0000-7000-8000-0000000000c9';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const BANNER_URL = 'https://cdn.mizita.test/booking-pages/banner.jpg';

    public const SOURCE_PATH = '/tmp/upload/banner.jpg';

    public const FILE_NAME = 'banner.jpg';

    public const MIME_TYPE = 'image/jpeg';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function page(
        string $id = self::PAGE_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        BrandColor $accentColor = BrandColor::Teal,
        ButtonShape $buttonShape = ButtonShape::Rounded,
        PageTheme $theme = PageTheme::Dark,
        ?DateTimeImmutable $createdAt = null,
    ): BookingPage {
        return BookingPage::restore(
            id: $id,
            businessId: $businessId,
            accentColor: $accentColor,
            buttonShape: $buttonShape,
            theme: $theme,
            createdAt: $createdAt ?? self::now(),
        );
    }

    public static function image(
        string $id = self::IMAGE_ID,
        string $url = 'https://cdn.mizita.test/booking-pages/one.jpg',
        int $position = 1,
    ): BookingPageImage {
        return new BookingPageImage(id: $id, url: $url, position: $position);
    }

    public static function attachInput(
        string $sourcePath = self::SOURCE_PATH,
        string $fileName = self::FILE_NAME,
        string $mimeType = self::MIME_TYPE,
        int $sizeInBytes = 1024,
    ): AttachBookingPageImageInput {
        return new AttachBookingPageImageInput(
            sourcePath: $sourcePath,
            fileName: $fileName,
            mimeType: $mimeType,
            sizeInBytes: $sizeInBytes,
        );
    }
}
