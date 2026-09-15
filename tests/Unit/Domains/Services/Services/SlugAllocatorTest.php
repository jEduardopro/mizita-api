<?php

declare(strict_types=1);

use App\Domains\Services\Services\SlugAllocator;
use App\Domains\Services\ValueObjects\Slug;

beforeEach(function () {
    $this->allocator = new SlugAllocator;
    $this->allocate = fn (array $taken, string $base = 'corte-de-pelo'): string => $this->allocator
        ->allocate(Slug::restore($base), $taken)->value;
});

it('hands back the base address when nothing has taken it', function () {
    expect(($this->allocate)([]))->toBe('corte-de-pelo');
});

it('hands back the base address when only unrelated addresses are taken', function () {
    expect(($this->allocate)(['corte-de-barba', 'corte-de-pelo-premium']))->toBe('corte-de-pelo');
});

it('numbers the address from two when the base is taken', function () {
    expect(($this->allocate)(['corte-de-pelo']))->toBe('corte-de-pelo-2');
});

it('walks past every number already taken', function () {
    expect(($this->allocate)(['corte-de-pelo', 'corte-de-pelo-2', 'corte-de-pelo-3']))
        ->toBe('corte-de-pelo-4');
});

it('fills the first gap in the numbering', function () {
    expect(($this->allocate)(['corte-de-pelo', 'corte-de-pelo-3']))->toBe('corte-de-pelo-2');
});

it('ignores a taken address that only starts like the base', function () {
    expect(($this->allocate)(['corte-de-pelo-premium', 'corte-de-pelo-2b']))->toBe('corte-de-pelo');
});

it('reads the numbers in any order they arrive', function () {
    expect(($this->allocate)(['corte-de-pelo-3', 'corte-de-pelo-2', 'corte-de-pelo']))
        ->toBe('corte-de-pelo-4');
});

it('keeps the numbered address within the length the column holds', function () {
    $allocated = ($this->allocate)([str_repeat('a', 60)], str_repeat('a', 60));

    expect(strlen($allocated))->toBe(60)
        ->and($allocated)->toBe(str_repeat('a', 58).'-2');
});
