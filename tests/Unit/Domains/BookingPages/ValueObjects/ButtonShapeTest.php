<?php

declare(strict_types=1);

use App\Domains\BookingPages\Exceptions\InvalidBookingPageButtonShape;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Shared\Contracts\DomainFailure;

describe('the shapes a booking button may take', function () {
    it('holds exactly the three shapes the front end can render', function () {
        expect(array_column(ButtonShape::cases(), 'value'))->toBe(['pill', 'rounded', 'rectangle']);
    });

    it('holds three shapes and no more', function () {
        expect(ButtonShape::cases())->toHaveCount(3);
    });

    it('opens on a pill, the shape a page is given before anybody chooses', function () {
        expect(ButtonShape::Pill->value)->toBe('pill')
            ->and(ButtonShape::cases()[0])->toBe(ButtonShape::Pill);
    });
});

describe('reading a shape off a string', function () {
    it('answers with the shape that string names', function (string $value, ButtonShape $shape) {
        expect(ButtonShape::fromValue($value))->toBe($shape);
    })->with([
        'pill' => ['pill', ButtonShape::Pill],
        'rounded' => ['rounded', ButtonShape::Rounded],
        'rectangle' => ['rectangle', ButtonShape::Rectangle],
    ]);

    it('forgives the case and the padding a caller sent', function (string $value) {
        expect(ButtonShape::fromValue($value))->toBe(ButtonShape::Rounded);
    })->with([
        'upper case' => 'ROUNDED',
        'mixed case' => 'RoUnDeD',
        'padded' => '  rounded  ',
    ]);

    it('refuses a shape the page cannot render', function (string $value) {
        expect(fn () => ButtonShape::fromValue($value))->toThrow(InvalidBookingPageButtonShape::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a shape from somewhere else' => 'square',
        'a css radius' => '9999px',
        'a near miss' => 'round',
    ]);

    it('refuses with a domain failure rather than with a raw value error', function () {
        $failure = null;

        try {
            ButtonShape::fromValue('square');
        } catch (Throwable $caught) {
            $failure = $caught;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure)->not->toBeInstanceOf(ValueError::class)
            ->and($failure)->toBeInstanceOf(InvalidBookingPageButtonShape::class);
    });

    it('names the value it turned down as it was written', function () {
        expect(fn () => ButtonShape::fromValue('Square'))
            ->toThrow(
                InvalidBookingPageButtonShape::class,
                '[Square] is not a button shape a booking page may be given.',
            );
    });

    it('takes every shape it publishes back off its own spelling', function (ButtonShape $shape) {
        expect(ButtonShape::fromValue($shape->value))->toBe($shape);
    })->with(ButtonShape::cases());
});
