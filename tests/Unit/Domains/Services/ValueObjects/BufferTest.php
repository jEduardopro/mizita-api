<?php

declare(strict_types=1);

use App\Domains\Services\Exceptions\InvalidServiceBuffer;
use App\Domains\Services\ValueObjects\Buffer;

it('accepts a buffer a service may reserve', function (int $minutes) {
    expect(Buffer::ofMinutes($minutes)->minutes)->toBe($minutes);
})->with([
    'none at all' => Buffer::MINIMUM_MINUTES,
    'ten minutes' => 10,
    'the longest' => Buffer::MAXIMUM_MINUTES,
]);

it('accepts no buffer, unlike a duration', function () {
    expect(Buffer::ofMinutes(0)->minutes)->toBe(0)
        ->and(Buffer::none()->minutes)->toBe(0)
        ->and(Buffer::none()->equals(Buffer::ofMinutes(0)))->toBeTrue();
});

it('rejects a negative buffer', function (int $minutes) {
    expect(fn () => Buffer::ofMinutes($minutes))->toThrow(InvalidServiceBuffer::class);
})->with(['minus one' => -1, 'far negative' => -600]);

it('rejects a buffer longer than a day', function (int $minutes) {
    expect(fn () => Buffer::ofMinutes($minutes))->toThrow(InvalidServiceBuffer::class);
})->with(['one minute past a day' => 1441, 'a week' => 10080]);

it('names the minutes it refused in the message', function () {
    expect(fn () => Buffer::ofMinutes(-1))->toThrow(InvalidServiceBuffer::class, '[-1]')
        ->and(fn () => Buffer::ofMinutes(5000))->toThrow(InvalidServiceBuffer::class, '[5000]');
});

it('restores a stored buffer without checking the bounds again', function () {
    expect(Buffer::restore(-30)->minutes)->toBe(-30);
});

it('compares two buffers by their minutes', function () {
    expect(Buffer::ofMinutes(10)->equals(Buffer::restore(10)))->toBeTrue()
        ->and(Buffer::ofMinutes(10)->equals(Buffer::none()))->toBeFalse();
});
