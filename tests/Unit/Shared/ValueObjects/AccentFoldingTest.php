<?php

declare(strict_types=1);

use App\Shared\ValueObjects\AccentFolding;

describe('folding a word so either spelling finds the other', function () {
    it('lowercases a word and strips its accents', function (string $raw, string $folded) {
        expect(AccentFolding::fold($raw))->toBe($folded);
    })->with([
        'an accented phrase' => ['Depilación LÁSER', 'depilacion laser'],
        'a tilde' => ['Niño', 'nino'],
        'a cedilla' => ['Façade', 'facade'],
        'a diaeresis' => ['Pingüino', 'pinguino'],
        'every vowel at once' => ['áéíóú', 'aeiou'],
    ]);

    it('leaves a word the database already stores plainly untouched', function (string $raw) {
        expect(AccentFolding::fold($raw))->toBe($raw);
    })->with([
        'a word' => 'corte',
        'a phrase' => 'corte de pelo',
        'a number' => '250',
        'a number and a unit' => '30 min',
        'punctuation' => 'corte-pelo_1%',
        'nothing at all' => '',
    ]);

    it('folds an accented capital, which the database collation leaves alone', function (string $raw, string $folded) {
        expect(AccentFolding::fold($raw))->toBe($folded);
    })->with([
        'a capital with an acute' => ['Á', 'a'],
        'a capital tilde' => ['Ñ', 'n'],
        'a capital diaeresis' => ['Ü', 'u'],
        'a whole capitalised word' => ['ÑOÑO', 'nono'],
    ]);
});

describe('handing the same map to the database', function () {
    it('lists as many replacements as it lists sources, which is what translate demands', function () {
        expect(mb_strlen(AccentFolding::sourceCharacters()))
            ->toBe(mb_strlen(AccentFolding::replacementCharacters()));
    });

    it('folds each source character to the replacement paired with it', function () {
        $sources = mb_str_split(AccentFolding::sourceCharacters());
        $replacements = mb_str_split(AccentFolding::replacementCharacters());

        foreach ($sources as $position => $source) {
            expect(AccentFolding::fold($source))->toBe($replacements[$position]);
        }
    });

    it('replaces every accent with a plain ascii letter', function () {
        expect(AccentFolding::replacementCharacters())->toMatch('/^[a-z]+$/');
    });

    it('names each source character once, since translate reads only the first', function () {
        $sources = mb_str_split(AccentFolding::sourceCharacters());

        expect(array_unique($sources))->toHaveCount(count($sources));
    });

    it('covers the accented capitals as well as the lowercase spellings', function () {
        $sources = AccentFolding::sourceCharacters();

        expect($sources)->toContain('Á')
            ->and($sources)->toContain('á')
            ->and($sources)->toContain('Ñ')
            ->and($sources)->toContain('ñ');
    });
});
