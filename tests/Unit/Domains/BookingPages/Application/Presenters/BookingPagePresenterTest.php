<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->images = new FakeBookingPageImages;
    $this->presenter = new BookingPagePresenter($this->images);
});

it('describes the page as the three choices the client renders', function () {
    $data = $this->presenter->describe(BookingPageFixtures::page());

    expect($data)->toBeInstanceOf(BookingPageData::class)
        ->and($data->accentColor)->toBe('teal')
        ->and($data->buttonShape)->toBe('rounded')
        ->and($data->theme)->toBe('dark');
});

it('hands the page out under its uuid, never a row number', function () {
    expect($this->presenter->describe(BookingPageFixtures::page())->id)->toBe(BookingPageFixtures::PAGE_ID);
});

it('sends the enum values out as strings the client can read', function () {
    $page = BookingPageFixtures::page(
        accentColor: BrandColor::Ink,
        buttonShape: ButtonShape::Pill,
        theme: PageTheme::Light,
    );

    $data = $this->presenter->describe($page);

    expect($data->accentColor)->toBeString()
        ->and($data->accentColor)->toBe('ink')
        ->and($data->buttonShape)->toBe('pill')
        ->and($data->theme)->toBe('light');
});

it('describes a page with no banner and no gallery as empty rather than absent', function () {
    $data = $this->presenter->describe(BookingPageFixtures::page());

    expect($data->bannerUrl)->toBeNull()
        ->and($data->gallery)->toBe([]);
});

it('carries the banner url when the page has one', function () {
    $this->images->withBanner(BookingPageFixtures::page(), BookingPageFixtures::BANNER_URL);

    expect($this->presenter->describe(BookingPageFixtures::page())->bannerUrl)
        ->toBe(BookingPageFixtures::BANNER_URL);
});

it('carries the gallery in the order the media library kept it', function () {
    $this->images->withGallery(
        BookingPageFixtures::page(),
        BookingPageFixtures::image(BookingPageFixtures::IMAGE_ID, position: 1),
        BookingPageFixtures::image(BookingPageFixtures::SECOND_IMAGE_ID, position: 2),
    );

    $gallery = $this->presenter->describe(BookingPageFixtures::page())->gallery;

    expect($gallery)->toHaveCount(2)
        ->and($gallery[0]->id)->toBe(BookingPageFixtures::IMAGE_ID)
        ->and($gallery[0]->position)->toBe(1)
        ->and($gallery[1]->id)->toBe(BookingPageFixtures::SECOND_IMAGE_ID)
        ->and($gallery[1]->position)->toBe(2);
});

it('asks the media port about the page it was given and about no other', function () {
    $this->images->withBanner(BookingPageFixtures::page(), BookingPageFixtures::BANNER_URL);

    $data = $this->presenter->describe(BookingPageFixtures::page(id: BookingPageFixtures::GENERATED_PAGE_ID));

    expect($data->bannerUrl)->toBeNull()
        ->and($this->images->galleryReads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'bookingPageId' => BookingPageFixtures::GENERATED_PAGE_ID,
        ]]);
});

describe('the business the media is read under', function () {
    it('reads the media under the business the page itself carries', function () {
        $page = BookingPageFixtures::page(
            id: BookingPageFixtures::OTHER_PAGE_ID,
            businessId: BookingPageFixtures::OTHER_BUSINESS_ID,
        );

        $this->images->withBanner($page, BookingPageFixtures::BANNER_URL)->withGalleryOf($page, 2);

        $data = $this->presenter->describe($page);

        $read = [
            'businessId' => BookingPageFixtures::OTHER_BUSINESS_ID,
            'bookingPageId' => BookingPageFixtures::OTHER_PAGE_ID,
        ];

        expect($data->bannerUrl)->toBe(BookingPageFixtures::BANNER_URL)
            ->and($data->gallery)->toHaveCount(2)
            ->and($this->images->bannerReads)->toBe([$read])
            ->and($this->images->galleryReads)->toBe([$read]);
    });

    it('shows nothing of the media another business holds under the same page uuid', function () {
        $this->images
            ->withBanner(BookingPageFixtures::page(), BookingPageFixtures::BANNER_URL)
            ->withGalleryOf(BookingPageFixtures::page(), 3);

        $data = $this->presenter->describe(
            BookingPageFixtures::page(businessId: BookingPageFixtures::OTHER_BUSINESS_ID),
        );

        expect($data->bannerUrl)->toBeNull()
            ->and($data->gallery)->toBe([]);
    });

    it('takes the business from the page, so an anonymous visit needs no context bound', function () {
        $constructor = (new ReflectionClass(BookingPagePresenter::class))->getConstructor();

        expect(array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $constructor?->getParameters() ?? [],
        ))->toBe([BookingPageImages::class]);
    });
});
