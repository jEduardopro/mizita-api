<?php

declare(strict_types=1);

use App\Domains\Services\Exceptions\InvalidServiceDuration;
use App\Domains\Services\ValueObjects\Duration;

it('accepts a length of time a service may take', function (int $minutes) {
    expect(Duration::ofMinutes($minutes)->minutes)->toBe($minutes);
})->with([
    'the shortest' => Duration::MINIMUM_MINUTES,
    'a quarter of an hour' => 15,
    'an hour and a half' => 90,
    'the longest' => Duration::MAXIMUM_MINUTES,
]);

it('rejects a service shorter than it may last', function (int $minutes) {
    expect(fn () => Duration::ofMinutes($minutes))->toThrow(InvalidServiceDuration::class);
})->with(['zero' => 0, 'negative' => -1, 'far negative' => -600]);

it('rejects a service longer than a day', function (int $minutes) {
    expect(fn () => Duration::ofMinutes($minutes))->toThrow(InvalidServiceDuration::class);
})->with(['one minute past a day' => 1441, 'a week' => 10080]);

it('names the minutes it refused in the message', function () {
    expect(fn () => Duration::ofMinutes(0))->toThrow(InvalidServiceDuration::class, '[0]')
        ->and(fn () => Duration::ofMinutes(5000))->toThrow(InvalidServiceDuration::class, '[5000]');
});

it('restores a stored length without checking the bounds again', function () {
    expect(Duration::restore(0)->minutes)->toBe(0)
        ->and(Duration::restore(100000)->minutes)->toBe(100000);
});

it('compares two durations by their minutes', function () {
    expect(Duration::ofMinutes(45)->equals(Duration::restore(45)))->toBeTrue()
        ->and(Duration::ofMinutes(45)->equals(Duration::ofMinutes(46)))->toBeFalse();
});
