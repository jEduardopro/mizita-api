<?php

declare(strict_types=1);

use App\Domains\BookingPages\Exceptions\InvalidBookingPageTheme;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use App\Shared\Contracts\DomainFailure;

describe('the themes a booking page may wear', function () {
    it('holds exactly the three themes the front end can render', function () {
        expect(array_column(PageTheme::cases(), 'value'))->toBe(['system', 'light', 'dark']);
    });

    it('holds three themes and no more', function () {
        expect(PageTheme::cases())->toHaveCount(3);
    });

    it('offers following the reader device as well as the two fixed themes', function () {
        expect(PageTheme::System->value)->toBe('system')
            ->and(PageTheme::Light->value)->toBe('light')
            ->and(PageTheme::Dark->value)->toBe('dark');
    });
});

describe('reading a theme off a string', function () {
    it('answers with the theme that string names', function (string $value, PageTheme $theme) {
        expect(PageTheme::fromValue($value))->toBe($theme);
    })->with([
        'system' => ['system', PageTheme::System],
        'light' => ['light', PageTheme::Light],
        'dark' => ['dark', PageTheme::Dark],
    ]);

    it('forgives the case and the padding a caller sent', function (string $value) {
        expect(PageTheme::fromValue($value))->toBe(PageTheme::Dark);
    })->with([
        'upper case' => 'DARK',
        'mixed case' => 'DaRk',
        'padded' => '  dark  ',
    ]);

    it('refuses a theme the page cannot wear', function (string $value) {
        expect(fn () => PageTheme::fromValue($value))->toThrow(InvalidBookingPageTheme::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a theme from somewhere else' => 'sepia',
        'a media query' => 'prefers-color-scheme',
        'a near miss' => 'darkmode',
    ]);

    it('refuses with a domain failure rather than with a raw value error', function () {
        $failure = null;

        try {
            PageTheme::fromValue('sepia');
        } catch (Throwable $caught) {
            $failure = $caught;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure)->not->toBeInstanceOf(ValueError::class)
            ->and($failure)->toBeInstanceOf(InvalidBookingPageTheme::class);
    });

    it('names the value it turned down as it was written', function () {
        expect(fn () => PageTheme::fromValue('Sepia'))
            ->toThrow(InvalidBookingPageTheme::class, '[Sepia] is not a theme a booking page may be given.');
    });

    it('takes every theme it publishes back off its own spelling', function (PageTheme $theme) {
        expect(PageTheme::fromValue($theme->value))->toBe($theme);
    })->with(PageTheme::cases());
});
