<?php

declare(strict_types=1);

use App\Domains\BookingPages\Exceptions\InvalidBookingPageAccentColor;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Shared\Contracts\DomainFailure;

describe('the palette a business may brand itself with', function () {
    it('holds exactly the ten colours the front end has tokens for', function () {
        expect(array_column(BrandColor::cases(), 'value'))->toBe([
            'ink',
            'red',
            'orange',
            'amber',
            'purple',
            'blue',
            'sand',
            'slate',
            'teal',
            'green',
        ]);
    });

    it('holds ten colours and no more, because the CSS map is keyed to them', function () {
        expect(BrandColor::cases())->toHaveCount(10);
    });

    it('spells every colour in lower case, which is what the column stores', function (BrandColor $color) {
        expect($color->value)->toBe(mb_strtolower($color->value));
    })->with(BrandColor::cases());

    it('opens on ink, the colour a page is given before anybody chooses', function () {
        expect(BrandColor::Ink->value)->toBe('ink')
            ->and(BrandColor::cases()[0])->toBe(BrandColor::Ink);
    });
});

describe('reading a colour off a string', function () {
    it('answers with the colour that string names', function (string $value, BrandColor $color) {
        expect(BrandColor::fromValue($value))->toBe($color);
    })->with([
        'ink' => ['ink', BrandColor::Ink],
        'teal' => ['teal', BrandColor::Teal],
        'green' => ['green', BrandColor::Green],
    ]);

    it('forgives the case and the padding a caller sent', function (string $value) {
        expect(BrandColor::fromValue($value))->toBe(BrandColor::Purple);
    })->with([
        'upper case' => 'PURPLE',
        'mixed case' => 'PuRpLe',
        'padded' => '  purple  ',
        'padded and upper case' => "\tPURPLE\n",
    ]);

    it('refuses a colour the palette does not hold', function (string $value) {
        expect(fn () => BrandColor::fromValue($value))->toThrow(InvalidBookingPageAccentColor::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a colour from somewhere else' => 'fuchsia',
        'a hex triplet' => '#ff0000',
        'a css variable' => 'var(--brand-accent)',
        'a near miss' => 'inky',
    ]);

    it('refuses with a domain failure rather than with a raw value error', function () {
        $failure = null;

        try {
            BrandColor::fromValue('fuchsia');
        } catch (Throwable $caught) {
            $failure = $caught;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure)->not->toBeInstanceOf(ValueError::class)
            ->and($failure)->toBeInstanceOf(InvalidBookingPageAccentColor::class);
    });

    it('names the value it turned down as it was written', function () {
        expect(fn () => BrandColor::fromValue('Fuchsia'))
            ->toThrow(
                InvalidBookingPageAccentColor::class,
                '[Fuchsia] is not a colour a booking page may be given.',
            );
    });

    it('takes every colour it publishes back off its own spelling', function (BrandColor $color) {
        expect(BrandColor::fromValue($color->value))->toBe($color);
    })->with(BrandColor::cases());
});
