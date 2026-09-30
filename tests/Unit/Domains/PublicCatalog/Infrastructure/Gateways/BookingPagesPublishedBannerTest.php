<?php

declare(strict_types=1);

use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPagesPublishedBanner;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeBookingPageRepository;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->pages = new FakeBookingPageRepository;
    $this->images = new FakeBookingPageImages;

    $this->gateway = new BookingPagesPublishedBanner($this->pages, $this->images);

    $this->read = fn (string $businessId = PublicCatalogFixtures::BUSINESS_ID): ?string => $this->gateway
        ->urlForBusiness($businessId);
});

describe('a business that has never opened its booking page', function () {
    it('answers with no banner', function () {
        expect(($this->read)())->toBeNull();
    });

    it('asks the image port nothing about a page that does not exist', function () {
        ($this->read)();

        expect($this->images->bannerReads)->toBe([]);
    });

    it('writes nothing, because an anonymous visit may not create a row', function () {
        ($this->read)();

        expect($this->pages->saved)->toBe([]);
    });

    it('reads through the nullable lookup, never the one that provisions a page', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(BookingPagesPublishedBanner::class, '__construct'))->getParameters(),
        );

        expect($types)->not->toContain(CurrentBookingPage::class)
            ->and($types)->toContain(BookingPageRepository::class);
    });
});

describe('a business with a booking page', function () {
    beforeEach(function () {
        $this->page = BookingPageFixtures::page(businessId: PublicCatalogFixtures::BUSINESS_ID);

        $this->pages->store($this->page);
    });

    it('publishes the banner the business uploaded', function () {
        $this->images->withBanner($this->page, PublicCatalogFixtures::BANNER_URL);

        expect(($this->read)())->toBe(PublicCatalogFixtures::BANNER_URL);
    });

    it('answers with no banner for a page that has none', function () {
        expect(($this->read)())->toBeNull();
    });

    it('asks the image port about the business and the page it just read, by uuid', function () {
        ($this->read)();

        expect($this->pages->businessIdsSeen)->toBe([PublicCatalogFixtures::BUSINESS_ID])
            ->and($this->images->bannerReads)->toBe([[
                'businessId' => PublicCatalogFixtures::BUSINESS_ID,
                'bookingPageId' => BookingPageFixtures::PAGE_ID,
            ]]);
    });

    it('publishes none of it for a neighbouring business whose page carries the same id', function () {
        $this->images->withBanner($this->page, PublicCatalogFixtures::BANNER_URL);
        $this->pages->store(BookingPageFixtures::page(businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID));

        expect(($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID))->toBeNull();
    });

    it('writes nothing while it reads a page that already exists', function () {
        ($this->read)();

        expect($this->pages->saved)->toBe([]);
    });
});
