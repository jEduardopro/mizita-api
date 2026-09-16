<?php

declare(strict_types=1);

use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\FakeBusinessContext;

function createBookingPage(
    BrandColor $accentColor = BrandColor::Purple,
    ButtonShape $buttonShape = ButtonShape::Rectangle,
    PageTheme $theme = PageTheme::System,
): BookingPage {
    return BookingPage::create(
        id: BookingPageFixtures::PAGE_ID,
        businessId: FakeBusinessContext::BUSINESS_ID,
        accentColor: $accentColor,
        buttonShape: $buttonShape,
        theme: $theme,
        now: BookingPageFixtures::now(),
    );
}

describe('opening a booking page', function () {
    it('holds the look it was given, scoped to the business that asked', function () {
        $page = createBookingPage();

        expect($page->id)->toBe(BookingPageFixtures::PAGE_ID)
            ->and($page->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($page->accentColor())->toBe(BrandColor::Purple)
            ->and($page->buttonShape())->toBe(ButtonShape::Rectangle)
            ->and($page->theme())->toBe(PageTheme::System)
            ->and($page->createdAt)->toEqual(BookingPageFixtures::now());
    });

    it('carries the uuid it was handed as its identity, never a row number', function () {
        expect(createBookingPage()->id)->toBe(BookingPageFixtures::PAGE_ID)
            ->and(createBookingPage()->id)->toBeString();
    });
});

describe('the look a business gets on its first day', function () {
    it('opens in ink, with pill buttons, on a light page', function () {
        $page = BookingPage::withDefaults(
            BookingPageFixtures::PAGE_ID,
            FakeBusinessContext::BUSINESS_ID,
            BookingPageFixtures::now(),
        );

        expect($page->accentColor())->toBe(BrandColor::Ink)
            ->and($page->buttonShape())->toBe(ButtonShape::Pill)
            ->and($page->theme())->toBe(PageTheme::Light);
    });

    it('publishes those three defaults as constants the rest of the slice reads', function () {
        expect(BookingPage::DEFAULT_ACCENT_COLOR)->toBe(BrandColor::Ink)
            ->and(BookingPage::DEFAULT_BUTTON_SHAPE)->toBe(ButtonShape::Pill)
            ->and(BookingPage::DEFAULT_THEME)->toBe(PageTheme::Light);
    });

    it('takes the identity, the business and the instant it was handed', function () {
        $page = BookingPage::withDefaults(
            BookingPageFixtures::GENERATED_PAGE_ID,
            BookingPageFixtures::OTHER_BUSINESS_ID,
            BookingPageFixtures::now(),
        );

        expect($page->id)->toBe(BookingPageFixtures::GENERATED_PAGE_ID)
            ->and($page->businessId)->toBe(BookingPageFixtures::OTHER_BUSINESS_ID)
            ->and($page->createdAt)->toEqual(BookingPageFixtures::now());
    });
});

describe('restoring a booking page from a row', function () {
    it('keeps the look and the instant the row held', function () {
        $page = BookingPageFixtures::page();

        expect($page->accentColor())->toBe(BrandColor::Teal)
            ->and($page->buttonShape())->toBe(ButtonShape::Rounded)
            ->and($page->theme())->toBe(PageTheme::Dark)
            ->and($page->createdAt)->toEqual(BookingPageFixtures::now());
    });

    it('reaches for no clock, taking the instant the row carried', function () {
        $createdAt = new DateTimeImmutable('2024-06-01T08:00:00+00:00');

        expect(BookingPageFixtures::page(createdAt: $createdAt)->createdAt)->toEqual($createdAt);
    });
});

describe('restyling a booking page', function () {
    it('takes all three choices at once', function () {
        $page = BookingPageFixtures::page();

        $page->restyle(BrandColor::Amber, ButtonShape::Rectangle, PageTheme::System);

        expect($page->accentColor())->toBe(BrandColor::Amber)
            ->and($page->buttonShape())->toBe(ButtonShape::Rectangle)
            ->and($page->theme())->toBe(PageTheme::System);
    });

    it('changes the accent on its own', function () {
        $page = BookingPageFixtures::page();

        $page->changeAccent(BrandColor::Sand);

        expect($page->accentColor())->toBe(BrandColor::Sand)
            ->and($page->buttonShape())->toBe(ButtonShape::Rounded)
            ->and($page->theme())->toBe(PageTheme::Dark);
    });

    it('changes the button shape on its own', function () {
        $page = BookingPageFixtures::page();

        $page->changeButtonShape(ButtonShape::Pill);

        expect($page->buttonShape())->toBe(ButtonShape::Pill)
            ->and($page->accentColor())->toBe(BrandColor::Teal)
            ->and($page->theme())->toBe(PageTheme::Dark);
    });

    it('changes the theme on its own', function () {
        $page = BookingPageFixtures::page();

        $page->changeTheme(PageTheme::Light);

        expect($page->theme())->toBe(PageTheme::Light)
            ->and($page->accentColor())->toBe(BrandColor::Teal)
            ->and($page->buttonShape())->toBe(ButtonShape::Rounded);
    });

    it('accepts being restyled to the look it already had', function () {
        $page = BookingPageFixtures::page();

        $page->restyle(BrandColor::Teal, ButtonShape::Rounded, PageTheme::Dark);

        expect($page->accentColor())->toBe(BrandColor::Teal)
            ->and($page->buttonShape())->toBe(ButtonShape::Rounded)
            ->and($page->theme())->toBe(PageTheme::Dark);
    });

    it('never moves the page to another business or gives it a new identity', function () {
        $page = BookingPageFixtures::page();

        $page->restyle(BrandColor::Green, ButtonShape::Pill, PageTheme::Light);

        expect($page->id)->toBe(BookingPageFixtures::PAGE_ID)
            ->and($page->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($page->createdAt)->toEqual(BookingPageFixtures::now());
    });

    it('accepts every colour the palette holds', function (BrandColor $color) {
        $page = BookingPageFixtures::page();

        $page->changeAccent($color);

        expect($page->accentColor())->toBe($color);
    })->with(BrandColor::cases());
});
