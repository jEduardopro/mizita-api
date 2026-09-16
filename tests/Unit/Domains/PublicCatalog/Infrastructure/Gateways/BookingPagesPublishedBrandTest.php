<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPagesPublishedBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicGalleryImage;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeBookingPageRepository;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->pages = new FakeBookingPageRepository;
    $this->images = new FakeBookingPageImages;

    $this->gateway = new BookingPagesPublishedBrand($this->pages, new BookingPagePresenter($this->images));

    $this->read = fn (string $businessId = PublicCatalogFixtures::BUSINESS_ID): PublicBrand => $this->gateway
        ->forBusiness($businessId);
});

describe('a business that has never opened its booking page', function () {
    it('writes nothing, because an anonymous visit may not create a row', function () {
        ($this->read)();

        expect($this->pages->saved)->toBe([]);
    });

    it('still writes nothing however many visitors arrive', function () {
        ($this->read)();
        ($this->read)();
        ($this->read)();

        expect($this->pages->saved)->toBe([]);
    });

    it('falls back to the styling the domain declares as its default', function () {
        $brand = ($this->read)();

        expect($brand)->toBeInstanceOf(PublicBrand::class)
            ->and($brand->accentColor)->toBe(BookingPage::DEFAULT_ACCENT_COLOR->value)
            ->and($brand->buttonShape)->toBe(BookingPage::DEFAULT_BUTTON_SHAPE->value)
            ->and($brand->theme)->toBe(BookingPage::DEFAULT_THEME->value);
    });

    it('shows no banner and an empty gallery rather than refusing to render', function () {
        $brand = ($this->read)();

        expect($brand->bannerUrl)->toBeNull()
            ->and($brand->gallery)->toBe([]);
    });

    it('asks no image port about a page that does not exist', function () {
        ($this->read)();

        expect($this->images->galleryReads)->toBe([]);
    });

    it('reads through the nullable lookup, never the one that provisions a page', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(BookingPagesPublishedBrand::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([BookingPageRepository::class, BookingPagePresenter::class])
            ->and($types)->not->toContain(CurrentBookingPage::class)
            ->and((string) (new ReflectionMethod(BookingPageRepository::class, 'findForBusiness'))->getReturnType())
            ->toBe('?'.BookingPage::class);
    });
});

describe('a business that styled its booking page', function () {
    beforeEach(function () {
        $this->pages->store(BookingPageFixtures::page(businessId: PublicCatalogFixtures::BUSINESS_ID));
    });

    it('publishes the styling the business chose', function () {
        $brand = ($this->read)();

        expect($brand->accentColor)->toBe('teal')
            ->and($brand->buttonShape)->toBe('rounded')
            ->and($brand->theme)->toBe('dark');
    });

    it('publishes the banner the business uploaded', function () {
        $this->images->withBanner(BookingPageFixtures::PAGE_ID, PublicCatalogFixtures::BANNER_URL);

        expect(($this->read)()->bannerUrl)->toBe(PublicCatalogFixtures::BANNER_URL);
    });

    it('publishes every gallery image with its uuid and its url, in the stored order', function () {
        $this->images->withGallery(
            BookingPageFixtures::PAGE_ID,
            BookingPageFixtures::image(id: PublicCatalogFixtures::IMAGE_ID, url: 'https://cdn.mizita.test/one.jpg', position: 1),
            BookingPageFixtures::image(id: PublicCatalogFixtures::SECOND_IMAGE_ID, url: 'https://cdn.mizita.test/two.jpg', position: 2),
        );

        $gallery = ($this->read)()->gallery;

        expect($gallery)->toHaveCount(2)
            ->and($gallery[0])->toBeInstanceOf(PublicGalleryImage::class)
            ->and($gallery[0]->id)->toBe(PublicCatalogFixtures::IMAGE_ID)
            ->and($gallery[0]->url)->toBe('https://cdn.mizita.test/one.jpg')
            ->and($gallery[1]->id)->toBe(PublicCatalogFixtures::SECOND_IMAGE_ID)
            ->and(array_filter(array_column($gallery, 'id'), is_numeric(...)))->toBe([]);
    });

    it('drops the position, which no visitor reads and the order already carries', function () {
        $this->images->withGalleryOf(BookingPageFixtures::PAGE_ID, 2);

        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicGalleryImage::class))->getProperties(),
        );

        expect($fields)->toBe(['id', 'url'])
            ->and(($this->read)()->gallery)->toHaveCount(2);
    });

    it('reads the page of the business it was asked about', function () {
        ($this->read)();

        expect($this->pages->businessIdsSeen)->toBe([PublicCatalogFixtures::BUSINESS_ID]);
    });

    it('serves the defaults for a neighbouring business that styled nothing', function () {
        $brand = ($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

        expect($brand->accentColor)->toBe(BookingPage::DEFAULT_ACCENT_COLOR->value)
            ->and($this->pages->saved)->toBe([]);
    });

    it('writes nothing while it reads a page that already exists', function () {
        ($this->read)();

        expect($this->pages->saved)->toBe([]);
    });
});
