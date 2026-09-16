<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidBookingPageAccentColor;
use App\Domains\BookingPages\Exceptions\InvalidBookingPageButtonShape;
use App\Domains\BookingPages\Exceptions\InvalidBookingPageTheme;
use App\Domains\BookingPages\Infrastructure\ProvisionedCurrentBookingPage;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use App\Domains\Businesses\Contracts\BookingPageSettings;
use App\Domains\Businesses\Infrastructure\Gateways\BookingPagesBookingPageSettings;
use App\Domains\Businesses\ValueObjects\BookingPageImageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageStyle;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeBookingPageRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

function provisionedPagesOver(FakeBookingPageRepository $pages): ProvisionedCurrentBookingPage
{
    return new ProvisionedCurrentBookingPage(
        $pages,
        new FixedIdGenerator(BookingPageFixtures::GENERATED_PAGE_ID),
        new FakeClock(BookingPageFixtures::now()),
    );
}

function bookingPageSettingsOver(
    FakeBookingPageRepository $pages,
    FakeBookingPageImages $images,
): BookingPagesBookingPageSettings {
    return new BookingPagesBookingPageSettings(
        provisionedPagesOver($pages),
        new BookingPagePresenter($images),
        $pages,
    );
}

function bookingPageStyle(
    string $accentColor = 'teal',
    string $buttonShape = 'rounded',
    string $theme = 'dark',
): BookingPageStyle {
    return new BookingPageStyle(
        accentColor: $accentColor,
        buttonShape: $buttonShape,
        theme: $theme,
    );
}

beforeEach(function () {
    $this->pages = new FakeBookingPageRepository;
    $this->images = new FakeBookingPageImages;

    $this->settings = bookingPageSettingsOver($this->pages, $this->images);

    $this->read = fn (): BookingPageSnapshot => $this->settings->forBusiness(FakeBusinessContext::BUSINESS_ID);

    $this->apply = fn (
        string $accentColor = 'teal',
        string $buttonShape = 'rounded',
        string $theme = 'dark',
        string $businessId = FakeBusinessContext::BUSINESS_ID,
    ): mixed => $this->settings->applyTo($businessId, bookingPageStyle($accentColor, $buttonShape, $theme));
});

describe('reading the booking page of a business that has none yet', function () {
    it('provisions the page rather than answering with nothing', function () {
        $snapshot = ($this->read)();

        expect($snapshot)->toBeInstanceOf(BookingPageSnapshot::class)
            ->and($this->pages->saved)->toHaveCount(1)
            ->and($this->pages->saved[0])->toBeInstanceOf(BookingPage::class)
            ->and($this->pages->saved[0]->id)->toBe(BookingPageFixtures::GENERATED_PAGE_ID)
            ->and($this->pages->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('styles the provisioned page with the defaults the domain declares', function () {
        $snapshot = ($this->read)();

        expect($snapshot->accentColor)->toBe(BookingPage::DEFAULT_ACCENT_COLOR->value)
            ->and($snapshot->buttonShape)->toBe(BookingPage::DEFAULT_BUTTON_SHAPE->value)
            ->and($snapshot->theme)->toBe(BookingPage::DEFAULT_THEME->value);
    });

    it('hands back an empty banner and an empty gallery for a page nobody has filled', function () {
        $snapshot = ($this->read)();

        expect($snapshot->bannerUrl)->toBeNull()
            ->and($snapshot->gallery)->toBe([]);
    });

    it('provisions one page only, however often the settings are read', function () {
        ($this->read)();
        ($this->read)();

        expect($this->pages->saved)->toHaveCount(1);
    });
});

describe('reading the booking page a business already styled', function () {
    beforeEach(function () {
        $this->pages->store(BookingPageFixtures::page());
    });

    it('translates the stored styling into the snapshot the business domain reads', function () {
        $snapshot = ($this->read)();

        expect($snapshot->accentColor)->toBe('teal')
            ->and($snapshot->buttonShape)->toBe('rounded')
            ->and($snapshot->theme)->toBe('dark')
            ->and($this->pages->saved)->toBe([]);
    });

    it('carries the banner the page has', function () {
        $this->images->withBanner(BookingPageFixtures::PAGE_ID, BookingPageFixtures::BANNER_URL);

        expect(($this->read)()->bannerUrl)->toBe(BookingPageFixtures::BANNER_URL);
    });

    it('translates every gallery image into a snapshot carrying its uuid and its url', function () {
        $this->images->withGallery(
            BookingPageFixtures::PAGE_ID,
            BookingPageFixtures::image(id: BookingPageFixtures::IMAGE_ID, url: 'https://cdn.mizita.test/one.jpg', position: 1),
            BookingPageFixtures::image(id: BookingPageFixtures::SECOND_IMAGE_ID, url: 'https://cdn.mizita.test/two.jpg', position: 2),
        );

        $gallery = ($this->read)()->gallery;

        expect($gallery)->toHaveCount(2)
            ->and($gallery[0])->toBeInstanceOf(BookingPageImageSnapshot::class)
            ->and($gallery[0]->id)->toBe(BookingPageFixtures::IMAGE_ID)
            ->and(is_numeric($gallery[0]->id))->toBeFalse()
            ->and($gallery[0]->url)->toBe('https://cdn.mizita.test/one.jpg')
            ->and($gallery[1]->id)->toBe(BookingPageFixtures::SECOND_IMAGE_ID);
    });

    it('keeps the gallery in the order the neighbour ordered it', function () {
        $this->images->withGalleryOf(BookingPageFixtures::PAGE_ID, 3);

        expect(array_map(
            static fn (BookingPageImageSnapshot $image): string => $image->url,
            ($this->read)()->gallery,
        ))->toBe([
            'https://cdn.mizita.test/booking-pages/'.BookingPageFixtures::PAGE_ID.'/1.jpg',
            'https://cdn.mizita.test/booking-pages/'.BookingPageFixtures::PAGE_ID.'/2.jpg',
            'https://cdn.mizita.test/booking-pages/'.BookingPageFixtures::PAGE_ID.'/3.jpg',
        ]);
    });

    it('reads the page of the business it was asked about', function () {
        expect($this->pages->businessIdsSeen)->toBe([]);

        ($this->read)();

        expect($this->pages->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('restyling the booking page', function () {
    beforeEach(function () {
        $this->pages->store(BookingPageFixtures::page());
    });

    it('applies the styling the business submitted and saves the page', function () {
        ($this->apply)(accentColor: 'amber', buttonShape: 'rectangle', theme: 'light');

        expect($this->pages->saved)->toHaveCount(1)
            ->and($this->pages->saved[0]->accentColor())->toBe(BrandColor::Amber)
            ->and($this->pages->saved[0]->buttonShape())->toBe(ButtonShape::Rectangle)
            ->and($this->pages->saved[0]->theme())->toBe(PageTheme::Light)
            ->and($this->pages->saved[0]->id)->toBe(BookingPageFixtures::PAGE_ID);
    });

    it('accepts styling values however the client cased or padded them', function () {
        ($this->apply)(accentColor: '  AMBER ', buttonShape: 'Rectangle', theme: ' LIGHT');

        expect($this->pages->saved[0]->accentColor())->toBe(BrandColor::Amber)
            ->and($this->pages->saved[0]->buttonShape())->toBe(ButtonShape::Rectangle)
            ->and($this->pages->saved[0]->theme())->toBe(PageTheme::Light);
    });

    it('restyles the page of the business the port was handed, never one picked from ambient state', function () {
        $this->pages->store(BookingPageFixtures::page(
            id: BookingPageFixtures::OTHER_PAGE_ID,
            businessId: BookingPageFixtures::OTHER_BUSINESS_ID,
        ));

        ($this->apply)(accentColor: 'amber', businessId: BookingPageFixtures::OTHER_BUSINESS_ID);

        expect($this->pages->businessIdsSeen)->toBe([BookingPageFixtures::OTHER_BUSINESS_ID])
            ->and($this->pages->saved)->toHaveCount(1)
            ->and($this->pages->saved[0]->id)->toBe(BookingPageFixtures::OTHER_PAGE_ID)
            ->and($this->pages->saved[0]->businessId)->toBe(BookingPageFixtures::OTHER_BUSINESS_ID)
            ->and($this->pages->saved[0]->accentColor())->toBe(BrandColor::Amber);
    });

    it('leaves the page of the business it was not asked about exactly as it was', function () {
        $this->pages->store(BookingPageFixtures::page(
            id: BookingPageFixtures::OTHER_PAGE_ID,
            businessId: BookingPageFixtures::OTHER_BUSINESS_ID,
        ));

        ($this->apply)(accentColor: 'amber', businessId: BookingPageFixtures::OTHER_BUSINESS_ID);

        expect(($this->read)()->accentColor)->toBe('teal');
    });

    it('provisions a page for the business it was handed when that business had none', function () {
        ($this->apply)(accentColor: 'amber', businessId: BookingPageFixtures::OTHER_BUSINESS_ID);

        expect($this->pages->saved)->toHaveCount(2)
            ->and($this->pages->saved[0]->id)->toBe(BookingPageFixtures::GENERATED_PAGE_ID)
            ->and($this->pages->saved[1]->businessId)->toBe(BookingPageFixtures::OTHER_BUSINESS_ID)
            ->and($this->pages->saved[1]->accentColor())->toBe(BrandColor::Amber);
    });

    it('refuses styling the domain does not know, and saves nothing', function (array $styling, string $exception) {
        expect(fn () => ($this->apply)(...$styling))->toThrow($exception)
            ->and($this->pages->saved)->toBe([]);
    })->with([
        'unknown colour' => [['accentColor' => 'chartreuse'], InvalidBookingPageAccentColor::class],
        'unknown shape' => [['buttonShape' => 'hexagon'], InvalidBookingPageButtonShape::class],
        'unknown theme' => [['theme' => 'sepia'], InvalidBookingPageTheme::class],
        'blank colour' => [['accentColor' => '   '], InvalidBookingPageAccentColor::class],
    ]);

    it('refuses styling as a domain failure the responder can classify', function () {
        try {
            ($this->apply)(accentColor: 'chartreuse');
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->errorCode())->toBe('invalid_booking_page_accent_color')
            ->and($thrown->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->pages->store(BookingPageFixtures::page());

        expect(($this->apply)())->toBeNull();
    });

    it('never declares a use case response on the port', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(BookingPageSettings::class))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class)
            ->and($returnTypes)->not->toContain('?'.UseCaseResponse::class);
    });

    it('declares void on the write the port exposes', function () {
        expect((string) (new ReflectionMethod(BookingPageSettings::class, 'applyTo'))->getReturnType())->toBe('void');
    });

    it('takes the styling as one value object, so no caller can transpose two loose strings', function () {
        expect(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
            (new ReflectionMethod(BookingPageSettings::class, 'applyTo'))->getParameters(),
        ))->toBe(['businessId:string', 'style:'.BookingPageStyle::class]);
    });

    it('lets a neighbour refusal out by throwing, so the surrounding transaction rolls back', function () {
        $failure = BookingPageNotFound::forBusiness(FakeBusinessContext::BUSINESS_ID);

        $current = Mockery::mock(CurrentBookingPage::class);
        $current->shouldReceive('forBusiness')->once()->andThrow($failure);

        $settings = new BookingPagesBookingPageSettings(
            $current,
            new BookingPagePresenter($this->images),
            $this->pages,
        );

        try {
            $settings->applyTo(FakeBusinessContext::BUSINESS_ID, bookingPageStyle());
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($failure)
            ->and($this->pages->saved)->toBe([]);
    });

    it('lets an infrastructure error from the write out by throwing, so nothing reads as a success', function () {
        $bug = new RuntimeException('the booking pages table is gone');

        $this->pages->store(BookingPageFixtures::page());

        $repository = Mockery::mock(BookingPageRepository::class);
        $repository->shouldReceive('save')->once()->andThrow($bug);

        $settings = new BookingPagesBookingPageSettings(
            provisionedPagesOver($this->pages),
            new BookingPagePresenter($this->images),
            $repository,
        );

        expect(fn () => $settings->applyTo(FakeBusinessContext::BUSINESS_ID, bookingPageStyle()))->toThrow($bug);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the booking pages table is gone');

        $current = Mockery::mock(CurrentBookingPage::class);
        $current->shouldReceive('forBusiness')->once()->andThrow($bug);

        $settings = new BookingPagesBookingPageSettings(
            $current,
            new BookingPagePresenter($this->images),
            $this->pages,
        );

        expect(fn () => $settings->forBusiness(FakeBusinessContext::BUSINESS_ID))->toThrow($bug);
    });
});
