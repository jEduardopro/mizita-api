<?php

declare(strict_types=1);

use App\Domains\Services\Exceptions\InvalidServiceSlug;
use App\Domains\Services\Exceptions\ServiceNameNotSluggable;
use App\Domains\Services\ValueObjects\Slug;

it('builds an address out of a service name', function (string $name, string $slug) {
    expect(Slug::fromName($name)->value)->toBe($slug);
})->with([
    'plain' => ['Corte de pelo', 'corte-de-pelo'],
    'accents' => ['Barbería Ñandú', 'barberia-nandu'],
    'punctuation' => ['Corte & Barba (Premium)', 'corte-barba-premium'],
    'padded' => ['   Corte   de   pelo   ', 'corte-de-pelo'],
    'digits' => ['Masaje 60 min', 'masaje-60-min'],
    'already a slug' => ['corte-de-pelo', 'corte-de-pelo'],
    'german sharp s' => ['Straße', 'strasse'],
    'ligature' => ['Cœur', 'coeur'],
]);

it('refuses a name that carries no sluggable character', function (string $name) {
    expect(Slug::tryFromName($name))->toBeNull()
        ->and(fn () => Slug::fromName($name))->toThrow(ServiceNameNotSluggable::class);
})->with([
    'empty' => '',
    'whitespace' => '   ',
    'punctuation only' => '!!!',
    'symbols' => '---',
    'han characters' => '美容',
]);

it('truncates a long name at a word boundary', function () {
    $slug = Slug::fromName(str_repeat('corte ', 20));

    expect(strlen($slug->value))->toBeLessThanOrEqual(60)
        ->and($slug->value)->toBe('corte-corte-corte-corte-corte-corte-corte-corte-corte-corte')
        ->and(str_ends_with($slug->value, '-'))->toBeFalse();
});

it('clips a single long word when there is no boundary to cut at', function () {
    $slug = Slug::fromName(str_repeat('a', 80));

    expect($slug->value)->toBe(str_repeat('a', 60));
});

it('accepts a stored address that still has the right shape', function (string $value) {
    expect(Slug::fromString($value)->value)->toBe($value);
})->with([
    'a word' => 'corte',
    'hyphenated' => 'corte-de-pelo',
    'with digits' => 'masaje-60',
    'the longest' => str_repeat('a', 60),
]);

it('refuses an address that does not have the shape of one', function (string $value) {
    expect(fn () => Slug::fromString($value))->toThrow(InvalidServiceSlug::class);
})->with([
    'empty' => '',
    'uppercase' => 'Corte',
    'accented' => 'barbería',
    'spaces' => 'corte de pelo',
    'leading hyphen' => '-corte',
    'trailing hyphen' => 'corte-',
    'double hyphen' => 'corte--pelo',
    'underscore' => 'corte_pelo',
    'slash' => 'corte/pelo',
    'too long' => str_repeat('a', 61),
]);

it('numbers an address when the base is taken', function () {
    expect(Slug::fromName('Corte de pelo')->withSuffix(2)->value)->toBe('corte-de-pelo-2')
        ->and(Slug::fromName('Corte de pelo')->withSuffix(11)->value)->toBe('corte-de-pelo-11');
});

it('keeps a numbered address within the length a column holds', function () {
    $numbered = Slug::restore(str_repeat('a', 60))->withSuffix(2);

    expect(strlen($numbered->value))->toBe(60)
        ->and($numbered->value)->toBe(str_repeat('a', 58).'-2');
});

it('never leaves a dangling hyphen when it makes room for the number', function () {
    $numbered = Slug::restore(str_repeat('a', 57).'-bb')->withSuffix(7);

    expect($numbered->value)->toBe(str_repeat('a', 57).'-7');
});

it('refuses a suffix below the first one it allocates', function (int $suffix) {
    expect(fn () => Slug::fromName('Corte')->withSuffix($suffix))->toThrow(InvalidArgumentException::class);
})->with(['one' => 1, 'zero' => 0, 'negative' => -3]);

it('restores a stored address without checking its shape', function () {
    expect(Slug::restore('NOT a slug')->value)->toBe('NOT a slug');
});

it('compares two addresses by their value', function () {
    expect(Slug::restore('corte')->equals(Slug::fromName('Corte')))->toBeTrue()
        ->and(Slug::restore('corte')->equals(Slug::restore('corte-2')))->toBeFalse();
});
