<?php

declare(strict_types=1);

use App\Shared\ValueObjects\SearchTerm;

const SERVICES_IGNORED_SEARCH_WORDS = [
    'min', 'mins', 'minute', 'minutes', 'minuto', 'minutos',
    'h', 'hr', 'hrs', 'hora', 'horas',
    'eur', 'usd', 'mxn',
];

describe('deciding there is nothing to search for', function () {
    it('carries no term when it was given nothing to search for', function (?string $raw) {
        expect(SearchTerm::of($raw))->toBeNull();
    })->with([
        'missing' => null,
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);

    it('carries no term when every word it was given is ignored', function () {
        expect(SearchTerm::of('min horas eur', SERVICES_IGNORED_SEARCH_WORDS))->toBeNull();
    });
});

describe('splitting a phrase into words', function () {
    it('finds each word of a phrase separately, which a single wildcard never did', function () {
        expect(SearchTerm::of('up tes')?->tokens())->toBe(['up', 'tes']);
    });

    it('collapses the whitespace between words', function (string $raw) {
        expect(SearchTerm::of($raw)?->tokens())->toBe(['up', 'tes']);
    })->with([
        'a run of spaces' => 'up   tes',
        'leading and trailing spaces' => '   up tes   ',
        'a tab' => "up\ttes",
        'a newline' => "up\ntes",
        'every kind at once' => "  up \t\n  tes  ",
    ]);

    it('keeps a single word as a single word', function () {
        expect(SearchTerm::of('depilacion')?->tokens())->toBe(['depilacion']);
    });

    it('leaves a number alone, so it can be read as a price or a duration', function () {
        expect(SearchTerm::of('corte 250')?->tokens())->toBe(['corte', '250']);
    });

    it('folds the case and the accents of a word, so either spelling finds the other', function () {
        expect(SearchTerm::of('Depilación LÁSER')?->tokens())->toBe(['depilacion', 'laser']);
    });

    it('folds a word whatever it was given', function (string $raw, array $tokens) {
        expect(SearchTerm::of($raw)?->tokens())->toBe($tokens);
    })->with([
        'uppercase' => ['CORTE', ['corte']],
        'capitalised' => ['Corte Pelo', ['corte', 'pelo']],
        'accented' => ['Niño', ['nino']],
        'already folded' => ['corte pelo', ['corte', 'pelo']],
    ]);
});

describe('dropping the words that mean nothing to a search', function () {
    it('drops a unit the user typed after a number', function () {
        expect(SearchTerm::of('corte 30 min', SERVICES_IGNORED_SEARCH_WORDS)?->tokens())->toBe(['corte', '30']);
    });

    it('drops an ignored word whatever its case and accents', function (string $raw) {
        expect(SearchTerm::of($raw, SERVICES_IGNORED_SEARCH_WORDS)?->tokens())->toBe(['corte']);
    })->with([
        'uppercase' => 'corte MINUTOS',
        'capitalised' => 'corte Mins',
        'mixed case' => 'corte HoRaS',
        'accented' => 'corte hörás',
        'a currency' => 'corte EUR',
    ]);

    it('ignores nothing when it was given no list to ignore', function () {
        expect(SearchTerm::of('corte 30 min')?->tokens())->toBe(['corte', '30', 'min']);
    });
});

describe('saying the same word twice', function () {
    it('keeps a repeated word once, however the caller spelled it', function (string $raw, array $tokens) {
        expect(SearchTerm::of($raw)?->tokens())->toBe($tokens);
    })->with([
        'the very same word' => ['corte corte', ['corte']],
        'a different case' => ['corte CORTE', ['corte']],
        'the accented spelling first' => ['Depilación depilacion', ['depilacion']],
        'the unaccented spelling first' => ['depilacion Depilación', ['depilacion']],
        'a repeat among others' => ['corte pelo Corte barba', ['corte', 'pelo', 'barba']],
    ]);
});

describe('refusing to grow without bound', function () {
    it('searches for the words it can serve and drops the rest', function () {
        $words = array_map(static fn (int $index): string => "word{$index}", range(1, SearchTerm::MAXIMUM_TOKENS + 5));

        $term = SearchTerm::of(implode(' ', $words));

        expect($term?->tokens())->toHaveCount(SearchTerm::MAXIMUM_TOKENS)
            ->and($term?->tokens())->toBe(array_slice($words, 0, SearchTerm::MAXIMUM_TOKENS));
    });

    it('keeps every word of a phrase it can serve whole', function () {
        $words = array_map(static fn (int $index): string => "word{$index}", range(1, SearchTerm::MAXIMUM_TOKENS));

        expect(SearchTerm::of(implode(' ', $words))?->tokens())->toBe($words);
    });

    it('counts towards the cap only the words it kept', function () {
        $words = array_map(static fn (int $index): string => "word{$index}", range(1, SearchTerm::MAXIMUM_TOKENS));
        $raw = 'min '.implode(' min ', $words).' min';

        expect(SearchTerm::of($raw, SERVICES_IGNORED_SEARCH_WORDS)?->tokens())->toBe($words);
    });
});

describe('remembering what it was asked', function () {
    it('reports the phrase it was given, tidied rather than byte for byte', function (string $raw, string $expected) {
        expect(SearchTerm::of($raw)?->raw())->toBe($expected);
    })->with([
        'untouched' => ['up tes', 'up tes'],
        'a run of spaces' => ['up   tes', 'up tes'],
        'leading and trailing spaces' => ['   up tes   ', 'up tes'],
        'a tab and a newline' => ["up\t\ntes", 'up tes'],
        'a single word' => ['  corte  ', 'corte'],
    ]);

    it('reports the phrase as typed, ignored words and repetitions included', function () {
        expect(SearchTerm::of('corte 30 min corte', SERVICES_IGNORED_SEARCH_WORDS)?->raw())->toBe('corte 30 min corte');
    });
});
