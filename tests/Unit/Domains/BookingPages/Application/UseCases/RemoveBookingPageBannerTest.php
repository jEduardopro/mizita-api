<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Application\UseCases\RemoveBookingPageBanner;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeBookingPageRepository;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->repository = (new FakeBookingPageRepository)->store(BookingPageFixtures::page());
    $this->images = (new FakeBookingPageImages)
        ->withBanner(BookingPageFixtures::PAGE_ID, BookingPageFixtures::BANNER_URL);

    $this->useCase = new RemoveBookingPageBanner(
        $this->repository,
        $this->images,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext,
    );
});

it('answers with the page now carrying no banner', function () {
    $data = $this->useCase->handle()->value();

    expect($data->id)->toBe(BookingPageFixtures::PAGE_ID)
        ->and($data->bannerUrl)->toBeNull();
});

it('tells the media port which page lost its banner', function () {
    $this->useCase->handle();

    expect($this->images->bannersRemoved)->toBe([BookingPageFixtures::PAGE_ID]);
});

it('reads the page of the business the caller is operating', function () {
    $this->useCase->handle();

    expect($this->repository->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('leaves the gallery alone', function () {
    $this->images->withGalleryOf(BookingPageFixtures::PAGE_ID, 2);

    expect($this->useCase->handle()->value()->gallery)->toHaveCount(2);
});

it('accepts being asked to remove a banner that was never there', function () {
    $images = new FakeBookingPageImages;
    $useCase = new RemoveBookingPageBanner(
        $this->repository,
        $images,
        new BookingPagePresenter($images),
        new FakeBusinessContext,
    );

    $response = $useCase->handle();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value()->bannerUrl)->toBeNull();
});

describe('a business with no page of its own', function () {
    beforeEach(function () {
        $this->useCase = new RemoveBookingPageBanner(
            new FakeBookingPageRepository,
            $this->images,
            new BookingPagePresenter($this->images),
            new FakeBusinessContext,
        );
    });

    it('refuses rather than opening a page just to strip it', function () {
        $response = $this->useCase->handle();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('touches no media when there is no page', function () {
        $this->useCase->handle();

        expect($this->images->bannersRemoved)->toBe([]);
    });
});

it('never reads another business page', function () {
    $useCase = new RemoveBookingPageBanner(
        $this->repository,
        $this->images,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext(BookingPageFixtures::OTHER_BUSINESS_ID),
    );

    $response = $useCase->handle();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('booking_page_not_found')
        ->and($this->images->bannersRemoved)->toBe([]);
});
