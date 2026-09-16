<?php

declare(strict_types=1);

use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Shared\Contracts\DomainFailure;

describe('reading a time off a string', function () {
    it('takes the hour and the minute it was written with', function () {
        $time = TimeOfDay::fromString('09:30');

        expect($time->hour)->toBe(9)
            ->and($time->minute)->toBe(30);
    });

    it('accepts the seconds a time column hands back and drops them', function () {
        expect(TimeOfDay::fromString('09:30:00')->toString())->toBe('09:30')
            ->and(TimeOfDay::fromString('09:30:45')->minute)->toBe(30);
    });

    it('accepts an hour written without its leading zero', function () {
        expect(TimeOfDay::fromString('9:05')->hour)->toBe(9)
            ->and(TimeOfDay::fromString('9:05')->minute)->toBe(5);
    });

    it('ignores the padding around what it was handed', function () {
        expect(TimeOfDay::fromString("  09:30\t")->toString())->toBe('09:30');
    });

    it('holds midnight, the first minute a day has', function () {
        $midnight = TimeOfDay::fromString('00:00');

        expect($midnight->hour)->toBe(0)
            ->and($midnight->minute)->toBe(0)
            ->and($midnight->minutesFromMidnight())->toBe(0)
            ->and($midnight->toString())->toBe('00:00');
    });

    it('holds the last minute a day has', function () {
        $lastMinute = TimeOfDay::fromString('23:59');

        expect($lastMinute->minutesFromMidnight())->toBe(1439)
            ->and($lastMinute->toString())->toBe('23:59');
    });

    it('refuses something that is not written as a time at all', function (string $value) {
        expect(fn () => TimeOfDay::fromString($value))->toThrow(InvalidTimeOfDay::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a word' => 'noon',
        'an hour alone' => '9',
        'a one digit minute' => '09:5',
        'a three digit minute' => '09:300',
        'a dotted time' => '09.30',
        'digits with no separator' => '0930',
        'a negative hour' => '-1:00',
        'an offset glued on' => '09:30+02:00',
        'a full instant' => '2026-01-01 09:30',
    ]);

    it('refuses a time outside the hours a day holds', function (string $value) {
        expect(fn () => TimeOfDay::fromString($value))->toThrow(InvalidTimeOfDay::class);
    })->with([
        'the hour after the last' => '24:00',
        'well past the last hour' => '99:00',
        'the minute after the last' => '09:60',
        'well past the last minute' => '09:99',
    ]);

    it('says which of the two refusals it made', function () {
        expect(fn () => TimeOfDay::fromString('noon'))
            ->toThrow(InvalidTimeOfDay::class, '[noon] is not a time of day written as HH:MM.')
            ->and(fn () => TimeOfDay::fromString('24:00'))
            ->toThrow(InvalidTimeOfDay::class, '[24:00] is outside the hours a day holds.');
    });

    it('refuses as a domain failure the caller is shown a sentence for', function () {
        $failure = null;

        try {
            TimeOfDay::fromString('25:00');
        } catch (InvalidTimeOfDay $caught) {
            $failure = $caught;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('invalid_time_of_day');
    });
});

describe('restoring a time from a row', function () {
    it('takes the hour and the minute the column held', function () {
        $time = TimeOfDay::restore(14, 45);

        expect($time->hour)->toBe(14)
            ->and($time->minute)->toBe(45)
            ->and($time->toString())->toBe('14:45');
    });

    it('skips the range check the parser makes, so a stored oddity still reads back', function () {
        expect(TimeOfDay::restore(30, 90)->minutesFromMidnight())->toBe(1890);
    });
});

describe('counting the minutes since midnight', function () {
    it('turns the clock face into a number', function (string $value, int $minutes) {
        expect(TimeOfDay::fromString($value)->minutesFromMidnight())->toBe($minutes);
    })->with([
        'midnight' => ['00:00', 0],
        'one minute past midnight' => ['00:01', 1],
        'the top of an hour' => ['09:00', 540],
        'half past' => ['09:30', 570],
        'noon' => ['12:00', 720],
        'the last minute of the day' => ['23:59', 1439],
    ]);
});

describe('putting two times in order', function () {
    it('places an earlier time before a later one', function () {
        expect(TimeOfDay::fromString('09:00')->isBefore(TimeOfDay::fromString('09:01')))->toBeTrue();
    });

    it('does not place a later time before an earlier one', function () {
        expect(TimeOfDay::fromString('09:01')->isBefore(TimeOfDay::fromString('09:00')))->toBeFalse();
    });

    it('does not place a time before itself, so the boundary is exclusive', function () {
        expect(TimeOfDay::fromString('14:00')->isBefore(TimeOfDay::fromString('14:00')))->toBeFalse();
    });

    it('reads two spellings of the same minute as the same time', function () {
        expect(TimeOfDay::fromString('9:05')->equals(TimeOfDay::fromString('09:05:00')))->toBeTrue();
    });

    it('reads a different minute as a different time', function () {
        expect(TimeOfDay::fromString('09:05')->equals(TimeOfDay::fromString('09:06')))->toBeFalse();
    });
});

describe('writing a time back out', function () {
    it('pads both halves to two digits', function () {
        expect(TimeOfDay::restore(9, 5)->toString())->toBe('09:05')
            ->and(TimeOfDay::restore(0, 0)->toString())->toBe('00:00');
    });

    it('survives a round trip through its own spelling', function (string $value) {
        expect(TimeOfDay::fromString(TimeOfDay::fromString($value)->toString())->minutesFromMidnight())
            ->toBe(TimeOfDay::fromString($value)->minutesFromMidnight());
    })->with(['00:00', '09:05', '14:00', '23:59']);

    it('carries no date and no zone, because a schedule is a local time fact', function () {
        expect(TimeOfDay::fromString('09:30')->toString())->toBe('09:30')
            ->and(TimeOfDay::fromString('09:30')->toString())->not->toContain('+')
            ->and(TimeOfDay::fromString('09:30')->toString())->not->toContain('T');
    });
});
