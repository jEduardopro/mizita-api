<?php

declare(strict_types=1);

use App\Domains\Staff\Exceptions\InvalidBookingSlug;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Shared\Contracts\DomainFailure;

describe('fromName', function () {
    it('builds a booking address out of the name of a person', function (string $name, string $slug) {
        expect(BookingSlug::fromName($name)->value)->toBe($slug);
    })->with([
        'plain' => ['Ada Lovelace', 'ada-lovelace'],
        'spanish accents' => ['José Pablo Núñez', 'jose-pablo-nunez'],
        'every spanish vowel with an accent' => ['Álvaro Éder Íñigo Óscar Úrsula', 'alvaro-eder-inigo-oscar-ursula'],
        'eñe in capitals' => ['MUÑOZ PEÑA', 'munoz-pena'],
        'diaeresis' => ['Agüero', 'aguero'],
        'cedilla' => ['François', 'francois'],
        'german sharp s' => ['Weiß', 'weiss'],
        'ligature' => ['Cœur Æsir', 'coeur-aesir'],
        'apostrophe and dot' => ["María O'Neil Jr.", 'maria-o-neil-jr'],
        'padded and repeated spaces' => ['   Ada    Lovelace   ', 'ada-lovelace'],
        'digits' => ['Ada 2', 'ada-2'],
        'already a slug' => ['ada-lovelace', 'ada-lovelace'],
    ]);

    it('falls back to a generic address for a name that slugifies to nothing', function (string $name) {
        expect(BookingSlug::fromName($name)->value)->toBe('staff');
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'punctuation only' => '!!!',
        'hyphens only' => '---',
        'han characters' => '李小龍',
        'emoji' => '💈',
    ]);

    it('always produces an address its own strict constructor accepts', function (string $name) {
        $slug = BookingSlug::fromName($name);

        expect(BookingSlug::fromString($slug->value)->value)->toBe($slug->value);
    })->with([
        'accented' => 'José Pablo Núñez',
        'fallback' => '李小龍',
        'long' => str_repeat('Núñez ', 20),
    ]);

    it('truncates a long name at a word boundary, never leaving a trailing hyphen', function () {
        $slug = BookingSlug::fromName(str_repeat('ana ', 30));

        expect(strlen($slug->value))->toBeLessThanOrEqual(BookingSlug::MAXIMUM_LENGTH)
            ->and($slug->value)->toBe(rtrim(str_repeat('ana-', 15), '-'))
            ->and(str_ends_with($slug->value, '-'))->toBeFalse();
    });

    it('clips a single long word when there is no boundary to cut at', function () {
        expect(BookingSlug::fromName(str_repeat('a', 80))->value)->toBe(str_repeat('a', 60));
    });
});

describe('fromString', function () {
    it('accepts an address that has the shape of one', function (string $value) {
        expect(BookingSlug::fromString($value)->value)->toBe($value);
    })->with([
        'a word' => 'ada',
        'hyphenated' => 'ada-lovelace',
        'with digits' => 'ada-2',
        'digits only' => '2026',
        'the longest' => str_repeat('a', 60),
    ]);

    it('refuses an address that does not have the shape of one', function (string $value) {
        expect(fn () => BookingSlug::fromString($value))->toThrow(InvalidBookingSlug::class);
    })->with([
        'empty' => '',
        'uppercase' => 'Ada',
        'accented' => 'josé',
        'eñe' => 'muñoz',
        'spaces' => 'ada lovelace',
        'padded' => ' ada ',
        'leading hyphen' => '-ada',
        'trailing hyphen' => 'ada-',
        'double hyphen' => 'ada--lovelace',
        'underscore' => 'ada_lovelace',
        'slash' => 'ada/lovelace',
        'dot' => 'ada.lovelace',
        'one past the longest' => str_repeat('a', 61),
    ]);

    it('refuses an address that ends in a newline', function () {
        expect(fn () => BookingSlug::fromString("ada\n"))->toThrow(InvalidBookingSlug::class);
    });

    it('refuses with a domain failure that names the value', function () {
        expect(fn () => BookingSlug::fromString('Ada Lovelace'))
            ->toThrow(InvalidBookingSlug::class, '[Ada Lovelace] is not a valid booking link address.')
            ->and(InvalidBookingSlug::forValue('x'))->toBeInstanceOf(DomainFailure::class);
    });
});

describe('restore', function () {
    it('rehydrates a stored address without checking its shape', function () {
        expect(BookingSlug::restore('NOT a slug')->value)->toBe('NOT a slug');
    });
});

describe('withSuffix', function () {
    it('numbers an address when the base is taken', function () {
        expect(BookingSlug::fromName('José Pablo')->withSuffix(2)->value)->toBe('jose-pablo-2')
            ->and(BookingSlug::fromName('José Pablo')->withSuffix(11)->value)->toBe('jose-pablo-11');
    });

    it('keeps a numbered address within the length the column holds', function () {
        $numbered = BookingSlug::restore(str_repeat('a', 60))->withSuffix(2);

        expect(strlen($numbered->value))->toBe(60)
            ->and($numbered->value)->toBe(str_repeat('a', 58).'-2');
    });

    it('never leaves a dangling hyphen when it makes room for the number', function () {
        $numbered = BookingSlug::restore(str_repeat('a', 57).'-bb')->withSuffix(7);

        expect($numbered->value)->toBe(str_repeat('a', 57).'-7');
    });

    it('refuses a suffix below the first one it allocates', function (int $suffix) {
        expect(fn () => BookingSlug::fromName('Ada')->withSuffix($suffix))->toThrow(InvalidArgumentException::class);
    })->with(['one' => 1, 'zero' => 0, 'negative' => -3]);

    it('leaves the address it was called on untouched', function () {
        $base = BookingSlug::fromName('Ada');

        $base->withSuffix(2);

        expect($base->value)->toBe('ada');
    });
});

describe('equals', function () {
    it('compares two addresses by their value', function () {
        expect(BookingSlug::restore('ada')->equals(BookingSlug::fromName('Ada')))->toBeTrue()
            ->and(BookingSlug::restore('ada')->equals(BookingSlug::restore('ada-2')))->toBeFalse();
    });
});
