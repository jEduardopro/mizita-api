<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionEndDate;
use App\Domains\Subscriptions\ValueObjects\LastIncludedDay;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

function exclusiveEndOfDay(string $date, string $zone): DateTimeImmutable
{
    return LastIncludedDay::fromString($date)->exclusiveEndIn(new DateTimeZone($zone));
}

describe('parsing', function () {
    it('keeps a calendar date', function () {
        expect(LastIncludedDay::fromString('2026-06-30')->date)->toBe('2026-06-30');
    });

    it('trims the surrounding whitespace', function (string $value) {
        expect(LastIncludedDay::fromString($value)->date)->toBe('2026-06-30');
    })->with(['leading' => '  2026-06-30', 'trailing' => "2026-06-30\n", 'tab' => "\t2026-06-30\t"]);

    it('accepts the leap day of a leap year', function () {
        expect(LastIncludedDay::fromString('2028-02-29')->date)->toBe('2028-02-29');
    });

    it('rejects a missing date', function (string $value) {
        expect(fn () => LastIncludedDay::fromString($value))->toThrow(InvalidSubscriptionEndDate::class);
    })->with(['empty' => '', 'spaces' => '   ', 'newline' => "\n"]);

    it('rejects anything that is not strictly a YYYY-MM-DD calendar date', function (string $value) {
        expect(fn () => LastIncludedDay::fromString($value))->toThrow(InvalidSubscriptionEndDate::class);
    })->with([
        'a day that does not exist' => '2026-02-30',
        'the leap day of a common year' => '2026-02-29',
        'a thirteenth month' => '2026-13-01',
        'day zero' => '2026-06-00',
        'unpadded month and day' => '2026-6-3',
        'no separators' => '20260630',
        'slashes' => '2026/06/30',
        'day first' => '30-06-2026',
        'a time attached' => '2026-06-30T00:00:00',
        'a space and a time' => '2026-06-30 12:00',
        'a zone attached' => '2026-06-30+02:00',
        'trailing text' => '2026-06-30x',
        'a relative word' => 'tomorrow',
        'a two digit year' => '26-06-30',
        'unicode digits' => '２０２６-06-30',
    ]);

    it('refuses a date as an invalid domain failure', function (string $value) {
        try {
            LastIncludedDay::fromString($value);
        } catch (InvalidSubscriptionEndDate $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('invalid_subscription_end_date')
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        $this->fail("[{$value}] was accepted as a last included day.");
    })->with(['missing' => '', 'malformed' => '2026-02-30']);
});

describe('the exclusive end', function () {
    it('ends at the first instant of the following local day, in UTC', function () {
        $end = exclusiveEndOfDay('2026-06-30', 'America/Mexico_City');

        expect($end->format(DATE_ATOM))->toBe('2026-07-01T06:00:00+00:00')
            ->and($end->getTimezone()->getName())->toBe('UTC');
    });

    it('rolls over a month, a year and a leap day', function (string $date, string $expected) {
        expect(exclusiveEndOfDay($date, 'UTC')->format(DATE_ATOM))->toBe($expected);
    })->with([
        'end of month' => ['2026-04-30', '2026-05-01T00:00:00+00:00'],
        'end of year' => ['2026-12-31', '2027-01-01T00:00:00+00:00'],
        'leap day' => ['2028-02-29', '2028-03-01T00:00:00+00:00'],
    ]);

    it('depends on the zone of the business, not on the zone of the server', function () {
        expect(exclusiveEndOfDay('2026-06-30', 'Asia/Tokyo')->format(DATE_ATOM))->toBe('2026-06-30T15:00:00+00:00')
            ->and(exclusiveEndOfDay('2026-06-30', 'Pacific/Honolulu')->format(DATE_ATOM))->toBe('2026-07-01T10:00:00+00:00');
    });

    describe('in Europe/Madrid, across daylight saving time', function () {
        it('ends the day before spring forward at its winter midnight', function () {
            expect(exclusiveEndOfDay('2026-03-28', 'Europe/Madrid')->format(DATE_ATOM))
                ->toBe('2026-03-28T23:00:00+00:00');
        });

        it('ends the spring forward day at its summer midnight', function () {
            expect(exclusiveEndOfDay('2026-03-29', 'Europe/Madrid')->format(DATE_ATOM))
                ->toBe('2026-03-29T22:00:00+00:00');
        });

        it('gives the spring forward day twenty three hours', function () {
            $length = exclusiveEndOfDay('2026-03-29', 'Europe/Madrid')->getTimestamp()
                - exclusiveEndOfDay('2026-03-28', 'Europe/Madrid')->getTimestamp();

            expect($length)->toBe(23 * 3600);
        });

        it('ends the day before fall back at its summer midnight', function () {
            expect(exclusiveEndOfDay('2026-10-24', 'Europe/Madrid')->format(DATE_ATOM))
                ->toBe('2026-10-24T22:00:00+00:00');
        });

        it('ends the fall back day at its winter midnight', function () {
            expect(exclusiveEndOfDay('2026-10-25', 'Europe/Madrid')->format(DATE_ATOM))
                ->toBe('2026-10-25T23:00:00+00:00');
        });

        it('gives the fall back day twenty five hours', function () {
            $length = exclusiveEndOfDay('2026-10-25', 'Europe/Madrid')->getTimestamp()
                - exclusiveEndOfDay('2026-10-24', 'Europe/Madrid')->getTimestamp();

            expect($length)->toBe(25 * 3600);
        });
    });

    describe('in America/Mexico_City, which keeps no daylight saving time', function () {
        it('ends every day six hours after UTC midnight, including both european DST days', function (string $date, string $expected) {
            expect(exclusiveEndOfDay($date, 'America/Mexico_City')->format(DATE_ATOM))->toBe($expected);
        })->with([
            'the european spring forward day' => ['2026-03-29', '2026-03-30T06:00:00+00:00'],
            'the european fall back day' => ['2026-10-25', '2026-10-26T06:00:00+00:00'],
            'the day Mexico used to spring forward' => ['2026-04-05', '2026-04-06T06:00:00+00:00'],
            'midsummer' => ['2026-07-15', '2026-07-16T06:00:00+00:00'],
        ]);

        it('gives every day twenty four hours', function (string $dayBefore, string $day) {
            $length = exclusiveEndOfDay($day, 'America/Mexico_City')->getTimestamp()
                - exclusiveEndOfDay($dayBefore, 'America/Mexico_City')->getTimestamp();

            expect($length)->toBe(24 * 3600);
        })->with([
            'spring' => ['2026-03-28', '2026-03-29'],
            'autumn' => ['2026-10-24', '2026-10-25'],
        ]);
    });

    describe('in a zone whose clocks skip midnight itself', function () {
        it('ends the day before the jump at the first instant of the jump day', function () {
            expect(exclusiveEndOfDay('2026-09-05', 'America/Santiago')->format(DATE_ATOM))
                ->toBe('2026-09-06T04:00:00+00:00');
        });

        it('ends the jump day at the following local midnight', function () {
            expect(exclusiveEndOfDay('2026-09-06', 'America/Santiago')->format(DATE_ATOM))
                ->toBe('2026-09-07T03:00:00+00:00');
        });
    });
});
