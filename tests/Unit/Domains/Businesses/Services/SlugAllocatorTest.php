<?php

declare(strict_types=1);

use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\Slug;

beforeEach(function () {
    $this->allocator = new SlugAllocator;
    $this->base = Slug::fromName('Barbería López');
});

it('hands out the base itself when nothing holds it', function () {
    expect($this->base->value)->toBe('barberia-lopez')
        ->and($this->allocator->allocate($this->base, [])->value)->toBe('barberia-lopez');
});

it('starts numbering at two when the base is taken', function () {
    expect($this->allocator->allocate($this->base, ['barberia-lopez'])->value)->toBe('barberia-lopez-2');
});

it('takes the next number when the base and some variants are taken', function (array $taken, string $expected) {
    expect($this->allocator->allocate($this->base, $taken)->value)->toBe($expected);
})->with([
    'base and two' => [['barberia-lopez', 'barberia-lopez-2'], 'barberia-lopez-3'],
    'base and three in a row' => [['barberia-lopez', 'barberia-lopez-2', 'barberia-lopez-3'], 'barberia-lopez-4'],
    'unordered input' => [['barberia-lopez-3', 'barberia-lopez', 'barberia-lopez-2'], 'barberia-lopez-4'],
    'into double digits' => [
        [
            'barberia-lopez', 'barberia-lopez-2', 'barberia-lopez-3', 'barberia-lopez-4',
            'barberia-lopez-5', 'barberia-lopez-6', 'barberia-lopez-7', 'barberia-lopez-8',
            'barberia-lopez-9',
        ],
        'barberia-lopez-10',
    ],
]);

it('fills a gap in the sequence rather than counting past it', function () {
    expect($this->allocator->allocate($this->base, ['barberia-lopez', 'barberia-lopez-3'])->value)
        ->toBe('barberia-lopez-2');
});

it('hands out the bare base when only numbered variants are taken', function () {
    expect($this->allocator->allocate($this->base, ['barberia-lopez-2', 'barberia-lopez-3'])->value)
        ->toBe('barberia-lopez');
});

it('ignores a slug that merely starts with the base', function () {
    expect($this->allocator->allocate($this->base, ['barberia-lopez-madrid'])->value)
        ->toBe('barberia-lopez');

    expect($this->allocator->allocate($this->base, ['barberia-lopez', 'barberia-lopez-madrid'])->value)
        ->toBe('barberia-lopez-2');
});

it('ignores every near miss the repository may have handed it', function (string $candidate) {
    expect($this->allocator->allocate($this->base, [$candidate])->value)->toBe('barberia-lopez');
})->with([
    'a longer word' => 'barberia-lopeznandu',
    'a suffix that is not a number' => 'barberia-lopez-madrid',
    'a number inside a word' => 'barberia-lopez-2b',
    'a different base' => 'peluqueria-lopez',
    'the base as a prefix of a longer base' => 'barberia-lopez-2-madrid',
    'an unrelated slug' => 'studio-54',
]);

it('is not fooled by regex metacharacters in the base', function () {
    $dotted = Slug::restore('barberia.lopez');

    expect($this->allocator->allocate($dotted, ['barberiaxlopez'])->value)->toBe('barberia.lopez');
});

it('treats a suffix written with a leading zero as the number it spells', function () {
    expect($this->allocator->allocate($this->base, ['barberia-lopez', 'barberia-lopez-02'])->value)
        ->toBe('barberia-lopez-3');
});

it('shortens a base at the length limit so the numbered address still fits', function () {
    $long = Slug::fromName('Barbería La Esquina de Don José Luis Martínez en el Centro Histórico');

    $allocated = $this->allocator->allocate($long, [$long->value]);

    expect($allocated->value)->toBe('barberia-la-esquina-de-don-jose-luis-martinez-en-el-centro-2')
        ->and(strlen($allocated->value))->toBe(60);
});

it('returns the very base it was given when the base is free', function () {
    expect($this->allocator->allocate($this->base, []))->toBe($this->base);
});
