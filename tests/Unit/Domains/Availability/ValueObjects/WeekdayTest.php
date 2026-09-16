<?php

declare(strict_types=1);

use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\Contracts\DomainFailure;

describe('the numbers the week is stored with', function () {
    it('numbers every day the way ISO-8601 does', function (Weekday $weekday, int $number) {
        expect($weekday->value)->toBe($number);
    })->with([
        'Monday' => [Weekday::Monday, 1],
        'Tuesday' => [Weekday::Tuesday, 2],
        'Wednesday' => [Weekday::Wednesday, 3],
        'Thursday' => [Weekday::Thursday, 4],
        'Friday' => [Weekday::Friday, 5],
        'Saturday' => [Weekday::Saturday, 6],
        'Sunday' => [Weekday::Sunday, 7],
    ]);

    it('starts the week on Monday, not on Sunday', function () {
        expect(Weekday::Monday->value)->toBe(1)
            ->and(Weekday::Sunday->value)->toBe(7)
            ->and(Weekday::Sunday->value)->not->toBe(0);
    });

    it('holds seven days and no more', function () {
        expect(Weekday::cases())->toHaveCount(7)
            ->and(array_column(Weekday::cases(), 'value'))->toBe([1, 2, 3, 4, 5, 6, 7]);
    });
});

describe('reading a weekday off a number', function () {
    it('answers with the day that number stands for', function (int $number, Weekday $weekday) {
        expect(Weekday::fromNumber($number))->toBe($weekday);
    })->with([
        [1, Weekday::Monday],
        [2, Weekday::Tuesday],
        [3, Weekday::Wednesday],
        [4, Weekday::Thursday],
        [5, Weekday::Friday],
        [6, Weekday::Saturday],
        [7, Weekday::Sunday],
    ]);

    it('refuses a number no day of the week carries', function (int $number) {
        expect(fn () => Weekday::fromNumber($number))->toThrow(InvalidWeekday::class);
    })->with([
        'the Sunday other calendars call zero' => 0,
        'the day after the last' => 8,
        'a negative day' => -1,
        'a number from another scale' => 100,
    ]);

    it('refuses with a domain failure rather than with a raw value error', function () {
        $failure = null;

        try {
            Weekday::fromNumber(0);
        } catch (Throwable $caught) {
            $failure = $caught;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure)->not->toBeInstanceOf(ValueError::class)
            ->and($failure)->toBeInstanceOf(InvalidWeekday::class);
    });

    it('names the number it turned down', function () {
        expect(fn () => Weekday::fromNumber(8))
            ->toThrow(InvalidWeekday::class, '[8] is not a day of the week.');
    });
});
