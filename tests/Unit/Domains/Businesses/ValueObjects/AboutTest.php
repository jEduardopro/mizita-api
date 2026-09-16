<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessAbout;
use App\Domains\Businesses\ValueObjects\About;

describe('accepting a description', function () {
    it('keeps the text it was given', function () {
        expect(About::fromString('Barbería clásica desde 2019.')->value)->toBe('Barbería clásica desde 2019.');
    });

    it('trims what the caller padded, without touching the line breaks inside', function () {
        expect(About::fromString("  Cortes.\nY color.  ")->value)->toBe("Cortes.\nY color.");
    });

    it('keeps accents, punctuation and emoji, which are not a slug', function (string $text) {
        expect(About::fromString($text)->value)->toBe($text);
    })->with([
        'accents' => 'Barbería Ñandú',
        'punctuation' => 'Café & Té, S.L. — desde 2019',
        'another script' => 'Салон Красоты',
        'emoji' => 'Barbería 💈',
    ]);

    it('accepts a single character, since only emptiness is refused', function () {
        expect(About::fromString('X')->value)->toBe('X');
    });
});

describe('the length limit', function () {
    it('accepts a description of exactly the maximum length', function () {
        $text = str_repeat('a', 2000);

        expect(About::fromString($text)->value)->toBe($text)
            ->and(mb_strlen(About::fromString($text)->value))->toBe(About::MAXIMUM_LENGTH);
    });

    it('refuses a description one character past the maximum', function () {
        expect(fn () => About::fromString(str_repeat('a', 2001)))
            ->toThrow(InvalidBusinessAbout::class, 'A business description cannot be longer than 2000 characters.');
    });

    it('measures characters rather than bytes, so accents do not halve the limit', function () {
        expect(About::fromString(str_repeat('é', 2000))->value)->toBe(str_repeat('é', 2000));
    });

    it('measures what is left after trimming', function () {
        expect(About::fromString('   '.str_repeat('a', 2000).'   ')->value)->toBe(str_repeat('a', 2000));
    });
});

describe('refusing a description', function () {
    it('refuses an empty description', function (string $value) {
        expect(fn () => About::fromString($value))
            ->toThrow(InvalidBusinessAbout::class, 'A business description cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);
});

describe('collapsing an absent description', function () {
    it('answers null for everything that means no description', function (?string $value) {
        expect(About::fromNullable($value))->toBeNull();
    })->with([
        'null' => null,
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);

    it('builds the value object for text that says something', function () {
        expect(About::fromNullable('  Cortes y color.  '))->toBeInstanceOf(About::class)
            ->and(About::fromNullable('  Cortes y color.  ')?->value)->toBe('Cortes y color.');
    });

    it('still refuses text that is too long, because emptiness is the only rule it relaxes', function () {
        expect(fn () => About::fromNullable(str_repeat('a', 2001)))
            ->toThrow(InvalidBusinessAbout::class, 'A business description cannot be longer than 2000 characters.');
    });

    it('differs from fromString, which refuses emptiness rather than collapsing it', function () {
        expect(About::fromNullable(''))->toBeNull()
            ->and(fn () => About::fromString(''))->toThrow(InvalidBusinessAbout::class);
    });
});

describe('rehydrating from storage', function () {
    it('accepts a stored value fromString would refuse', function (string $stored) {
        expect(About::restore($stored)->value)->toBe($stored);
    })->with([
        'empty' => '',
        'untrimmed' => '  Cortes.  ',
        'longer than the limit is today' => str_repeat('a', 2001),
    ]);
});

describe('equality', function () {
    it('compares by value', function () {
        expect(About::fromString('Cortes.')->equals(About::fromString('Cortes.')))->toBeTrue()
            ->and(About::fromString('Cortes.')->equals(About::fromString('Color.')))->toBeFalse();
    });

    it('treats a padded description and its trimmed twin as one value', function () {
        expect(About::fromString('  Cortes.  ')->equals(About::fromString('Cortes.')))->toBeTrue();
    });
});
