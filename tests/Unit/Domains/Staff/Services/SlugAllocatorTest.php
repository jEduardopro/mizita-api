<?php

declare(strict_types=1);

use App\Domains\Staff\Services\SlugAllocator;
use App\Domains\Staff\ValueObjects\BookingSlug;

beforeEach(function () {
    $this->allocator = new SlugAllocator;
    $this->allocate = fn (array $taken, string $base = 'jose-pablo'): string => $this->allocator
        ->allocate(BookingSlug::restore($base), $taken)->value;
});

it('hands back the base address when nothing has taken it', function () {
    expect(($this->allocate)([]))->toBe('jose-pablo');
});

it('hands back the base address when only unrelated addresses are taken', function () {
    expect(($this->allocate)(['jose', 'jose-pablo-nunez', 'ana']))->toBe('jose-pablo');
});

it('hands back the base address when only numbered copies of it are taken', function () {
    expect(($this->allocate)(['jose-pablo-2', 'jose-pablo-3']))->toBe('jose-pablo');
});

it('numbers the address from two when the base is taken', function () {
    expect(($this->allocate)(['jose-pablo']))->toBe('jose-pablo-2');
});

it('walks past every number already taken', function () {
    expect(($this->allocate)(['jose-pablo', 'jose-pablo-2', 'jose-pablo-3']))->toBe('jose-pablo-4');
});

it('fills the lowest gap in the numbering', function () {
    expect(($this->allocate)(['jose-pablo', 'jose-pablo-3', 'jose-pablo-4']))->toBe('jose-pablo-2');
});

it('reads the numbers in any order they arrive', function () {
    expect(($this->allocate)(['jose-pablo-3', 'jose-pablo-2', 'jose-pablo']))->toBe('jose-pablo-4');
});

it('ignores a taken address that only starts like the base', function () {
    expect(($this->allocate)(['jose-pablo', 'jose-pablo-nunez', 'jose-pablo-2b']))->toBe('jose-pablo-2');
});

it('treats the base as a literal, not as a pattern', function () {
    expect(($this->allocate)(['a-b', 'aXb-2'], 'a-b'))->toBe('a-b-2');
});

it('keeps the numbered address within the length the column holds', function () {
    $allocated = ($this->allocate)([str_repeat('a', 60)], str_repeat('a', 60));

    expect(strlen($allocated))->toBe(60)
        ->and($allocated)->toBe(str_repeat('a', 58).'-2');
});

it('skips a truncated number that is already taken when the base runs to the full length', function () {
    $base = str_repeat('a', 60);

    expect(($this->allocate)([$base, str_repeat('a', 58).'-2'], $base))->toBe(str_repeat('a', 58).'-3');
});
