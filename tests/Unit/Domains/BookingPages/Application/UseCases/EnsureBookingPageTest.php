<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Application\UseCases\EnsureBookingPage;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeCurrentBookingPage;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->pages = new FakeCurrentBookingPage(BookingPageFixtures::page());
    $this->images = new FakeBookingPageImages;

    $this->useCase = new EnsureBookingPage(
        $this->pages,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext,
    );
});

it('answers with the page the business already has', function () {
    $data = $this->useCase->handle()->value();

    expect($data)->toBeInstanceOf(BookingPageData::class)
        ->and($data->id)->toBe(BookingPageFixtures::PAGE_ID)
        ->and($data->accentColor)->toBe('teal')
        ->and($data->buttonShape)->toBe('rounded')
        ->and($data->theme)->toBe('dark')
        ->and($data->bannerUrl)->toBeNull()
        ->and($data->gallery)->toBe([]);
});

it('asks for the page of the business the caller is operating', function () {
    $this->useCase->handle();

    expect($this->pages->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('never reaches for a business other than the one in context', function () {
    $useCase = new EnsureBookingPage(
        $this->pages,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext(BookingPageFixtures::OTHER_BUSINESS_ID),
    );

    $useCase->handle();

    expect($this->pages->businessIdsSeen)->toBe([BookingPageFixtures::OTHER_BUSINESS_ID]);
});

it('carries the media the page already holds', function () {
    $this->images
        ->withBanner(BookingPageFixtures::page(), BookingPageFixtures::BANNER_URL)
        ->withGalleryOf(BookingPageFixtures::page(), 2);

    $data = $this->useCase->handle()->value();

    expect($data->bannerUrl)->toBe(BookingPageFixtures::BANNER_URL)
        ->and($data->gallery)->toHaveCount(2);
});

it('succeeds, because a business that has never saved its settings still has a page', function () {
    expect($this->useCase->handle()->succeeded())->toBeTrue();
});
