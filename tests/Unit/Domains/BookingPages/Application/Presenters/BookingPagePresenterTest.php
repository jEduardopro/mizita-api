<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;

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
    $this->images->withBanner(BookingPageFixtures::PAGE_ID, BookingPageFixtures::BANNER_URL);

    expect($this->presenter->describe(BookingPageFixtures::page())->bannerUrl)
        ->toBe(BookingPageFixtures::BANNER_URL);
});

it('carries the gallery in the order the media library kept it', function () {
    $this->images->withGallery(
        BookingPageFixtures::PAGE_ID,
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
    $this->images->withBanner(BookingPageFixtures::PAGE_ID, BookingPageFixtures::BANNER_URL);

    $data = $this->presenter->describe(BookingPageFixtures::page(id: BookingPageFixtures::GENERATED_PAGE_ID));

    expect($data->bannerUrl)->toBeNull()
        ->and($this->images->galleryReads)->toBe([BookingPageFixtures::GENERATED_PAGE_ID]);
});
