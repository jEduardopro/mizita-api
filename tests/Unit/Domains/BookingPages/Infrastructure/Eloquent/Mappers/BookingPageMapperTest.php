<?php

declare(strict_types=1);

use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Infrastructure\Eloquent\Mappers\BookingPageMapper;
use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\FakeBusinessContext;

const BOOKING_PAGE_BUSINESS_KEY = 42;

/**
 * @param  array<string, mixed>  $overrides
 */
function bookingPageRow(array $overrides = []): BookingPageModel
{
    $model = new BookingPageModel;

    $model->setRawAttributes([
        'id' => 3,
        'uuid' => BookingPageFixtures::PAGE_ID,
        'business_id' => BOOKING_PAGE_BUSINESS_KEY,
        'accent_color' => 'teal',
        'button_shape' => 'rounded',
        'theme' => 'dark',
        'created_at' => BookingPageFixtures::now(),
        ...$overrides,
    ], true);

    return $model;
}

beforeEach(function () {
    $this->mapper = new BookingPageMapper;
});

describe('reading a row', function () {
    it('restores every choice the row carries', function () {
        $page = $this->mapper->toEntity(bookingPageRow(), FakeBusinessContext::BUSINESS_ID);

        expect($page)->toBeInstanceOf(BookingPage::class)
            ->and($page->id)->toBe(BookingPageFixtures::PAGE_ID)
            ->and($page->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($page->accentColor())->toBe(BrandColor::Teal)
            ->and($page->buttonShape())->toBe(ButtonShape::Rounded)
            ->and($page->theme())->toBe(PageTheme::Dark)
            ->and($page->createdAt)->toEqual(BookingPageFixtures::now());
    });

    it('takes the identity from the uuid column, not from the primary key', function () {
        expect($this->mapper->toEntity(bookingPageRow(), FakeBusinessContext::BUSINESS_ID)->id)
            ->toBe(BookingPageFixtures::PAGE_ID);
    });

    it('reads the business back as the uuid it was handed, never as the column', function () {
        $page = $this->mapper->toEntity(bookingPageRow(), FakeBusinessContext::BUSINESS_ID);

        expect($page->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($page->businessId)->not->toBe((string) BOOKING_PAGE_BUSINESS_KEY);
    });

    it('reads every colour back from the value the column stores', function (BrandColor $color) {
        expect($this->mapper->toEntity(
            bookingPageRow(['accent_color' => $color->value]),
            FakeBusinessContext::BUSINESS_ID,
        )->accentColor())->toBe($color);
    })->with(BrandColor::cases());

    it('reads every button shape back from the value the column stores', function (ButtonShape $shape) {
        expect($this->mapper->toEntity(
            bookingPageRow(['button_shape' => $shape->value]),
            FakeBusinessContext::BUSINESS_ID,
        )->buttonShape())->toBe($shape);
    })->with(ButtonShape::cases());

    it('reads every theme back from the value the column stores', function (PageTheme $theme) {
        expect($this->mapper->toEntity(
            bookingPageRow(['theme' => $theme->value]),
            FakeBusinessContext::BUSINESS_ID,
        )->theme())->toBe($theme);
    })->with(PageTheme::cases());
});

describe('writing a row', function () {
    it('spreads the page across its five columns', function () {
        expect($this->mapper->toAttributes(BookingPageFixtures::page(), BOOKING_PAGE_BUSINESS_KEY))->toBe([
            'uuid' => BookingPageFixtures::PAGE_ID,
            'business_id' => BOOKING_PAGE_BUSINESS_KEY,
            'accent_color' => 'teal',
            'button_shape' => 'rounded',
            'theme' => 'dark',
        ]);
    });

    it('writes the business as the int key it was handed', function () {
        $attributes = $this->mapper->toAttributes(BookingPageFixtures::page(), BOOKING_PAGE_BUSINESS_KEY);

        expect($attributes['business_id'])->toBeInt()
            ->and($attributes)->not->toContain(FakeBusinessContext::BUSINESS_ID);
    });

    it('writes the three choices as the strings the column holds', function () {
        $page = BookingPageFixtures::page(
            accentColor: BrandColor::Ink,
            buttonShape: ButtonShape::Pill,
            theme: PageTheme::Light,
        );

        $attributes = $this->mapper->toAttributes($page, BOOKING_PAGE_BUSINESS_KEY);

        expect($attributes['accent_color'])->toBe('ink')
            ->and($attributes['button_shape'])->toBe('pill')
            ->and($attributes['theme'])->toBe('light');
    });

    it('never writes the internal primary key or the creation instant', function () {
        $attributes = $this->mapper->toAttributes(BookingPageFixtures::page(), BOOKING_PAGE_BUSINESS_KEY);

        expect($attributes)->not->toHaveKey('id')
            ->and($attributes)->not->toHaveKey('created_at');
    });
});

it('survives a full round trip without losing a choice', function () {
    $page = BookingPageFixtures::page(
        accentColor: BrandColor::Sand,
        buttonShape: ButtonShape::Rectangle,
        theme: PageTheme::System,
    );

    $attributes = $this->mapper->toAttributes($page, BOOKING_PAGE_BUSINESS_KEY);

    $restored = $this->mapper->toEntity(
        bookingPageRow([...$attributes, 'created_at' => BookingPageFixtures::now()]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->id)->toBe($page->id)
        ->and($restored->businessId)->toBe($page->businessId)
        ->and($restored->accentColor())->toBe($page->accentColor())
        ->and($restored->buttonShape())->toBe($page->buttonShape())
        ->and($restored->theme())->toBe($page->theme())
        ->and($restored->createdAt)->toEqual($page->createdAt);
});
