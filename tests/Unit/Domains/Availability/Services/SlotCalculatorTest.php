<?php

declare(strict_types=1);

use App\Domains\Availability\Services\SlotCalculator;
use App\Domains\Availability\ValueObjects\AvailableDay;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Availability\ValueObjects\BookingBlock;
use App\Domains\Availability\ValueObjects\LocalDateRange;
use App\Domains\Availability\ValueObjects\SlotRules;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use Tests\Support\Availability\ScheduleFixtures;

const MADRID = 'Europe/Madrid';

const SPRING_FORWARD_SUNDAY = '2026-03-29';

const FALL_BACK_SUNDAY = '2026-10-25';

const PLAIN_TUESDAY = '2026-03-10';

const SUNDAY = 7;

const TUESDAY = 2;

const WEDNESDAY = 3;

/**
 * @param  list<AvailableDay>  $days
 * @return list<string>
 */
function utcStartsOf(array $days, int $index = 0): array
{
    return array_map(
        static fn (DateTimeImmutable $start): string => $start->format('H:i'),
        $days[$index]->starts,
    );
}

/**
 * @param  list<AvailableDay>  $days
 * @return list<string>
 */
function wallClockStartsOf(array $days, DateTimeZone $zone, int $index = 0): array
{
    return array_map(
        static fn (DateTimeImmutable $start): string => $start->setTimezone($zone)->format('H:i'),
        $days[$index]->starts,
    );
}

/**
 * @param  list<AvailableDay>  $days
 * @return list<int>
 */
function instantsOf(array $days, int $index = 0): array
{
    return array_map(
        static fn (DateTimeImmutable $start): int => $start->getTimestamp(),
        $days[$index]->starts,
    );
}

beforeEach(function () {
    $this->calculator = new SlotCalculator;
    $this->madrid = new DateTimeZone(MADRID);
    $this->utc = new DateTimeZone('UTC');

    $this->slotsOn = function (
        string $date,
        WeeklyIntervals $businessHours,
        ?WeeklyIntervals $staffHours = null,
        array $booked = [],
        ?BookingBlock $block = null,
        ?SlotRules $rules = null,
        ?DateTimeZone $zone = null,
        string $now = '2026-01-01T00:00:00+00:00',
        ?string $until = null,
    ): array {
        return $this->calculator->slotsBetween(
            LocalDateRange::between($date, $until ?? $date),
            $businessHours,
            $staffHours ?? $businessHours,
            $booked,
            $block ?? BookingBlock::lasting(30, 0, 0),
            $rules ?? new SlotRules(0, null, 30),
            $zone ?? $this->utc,
            new DateTimeImmutable($now),
        );
    };
});

describe('the grid a day is offered on', function () {
    it('anchors the grid to the interval start, never to midnight', function () {
        $days = ($this->slotsOn)(PLAIN_TUESDAY, ScheduleFixtures::weeklyIntervals([
            TUESDAY => [['09:30', '11:00']],
        ]));

        expect(utcStartsOf($days))->toBe(['09:30', '10:00', '10:30']);
    });

    it('offers no start whose whole block would run past closing time', function () {
        $days = ($this->slotsOn)(PLAIN_TUESDAY, ScheduleFixtures::weeklyIntervals([
            TUESDAY => [['09:00', '10:20']],
        ]));

        expect(utcStartsOf($days))->toBe(['09:00', '09:30'])
            ->and(utcStartsOf($days))->not->toContain('10:00');
    });

    it('walks the granularity the business chose, not the duration of the service', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '10:30']]]),
            rules: new SlotRules(0, null, 15),
        );

        expect(utcStartsOf($days))->toBe(['09:00', '09:15', '09:30', '09:45', '10:00']);
    });

    it('offers starts on each interval of a split shift, in order', function () {
        $days = ($this->slotsOn)(PLAIN_TUESDAY, ScheduleFixtures::weeklyIntervals([
            TUESDAY => [['09:00', '10:00'], ['16:00', '17:00']],
        ]));

        expect(utcStartsOf($days))->toBe(['09:00', '09:30', '16:00', '16:30']);
    });

    it('counts both buffers against the closing time, because a block is what a booking consumes', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '10:00']]]),
            block: BookingBlock::lasting(30, 10, 10),
        );

        expect(utcStartsOf($days))->toBe(['09:10']);
    });
});

describe('a day the clocks move forward', function () {
    it('offers no start inside the local hour that does not exist', function () {
        $days = ($this->slotsOn)(
            SPRING_FORWARD_SUNDAY,
            ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]),
            zone: new DateTimeZone(MADRID),
        );

        expect(wallClockStartsOf($days, $this->madrid))->not->toContain('02:00')
            ->and(wallClockStartsOf($days, $this->madrid))->not->toContain('02:30');
    });

    it('keeps offering the wall times on either side of the gap', function () {
        $days = ($this->slotsOn)(
            SPRING_FORWARD_SUNDAY,
            ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]),
            zone: new DateTimeZone(MADRID),
        );

        expect(wallClockStartsOf($days, $this->madrid))->toBe([
            '00:00', '00:30', '01:00', '01:30', '03:00', '03:30', '04:00', '04:30', '05:00', '05:30',
        ]);
    });

    it('offers no two starts at the same instant', function () {
        $days = ($this->slotsOn)(
            SPRING_FORWARD_SUNDAY,
            ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]),
            zone: new DateTimeZone(MADRID),
        );

        $instants = instantsOf($days);

        expect(array_values(array_unique($instants)))->toBe($instants)
            ->and($instants)->toHaveCount(10);
    });

    it('offers a shorter day than the same hours on an ordinary sunday', function () {
        $hours = ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]);
        $zone = new DateTimeZone(MADRID);

        $shifted = ($this->slotsOn)(SPRING_FORWARD_SUNDAY, $hours, zone: $zone);
        $ordinary = ($this->slotsOn)('2026-03-22', $hours, zone: $zone);

        expect($shifted[0]->starts)->toHaveCount(10)
            ->and($ordinary[0]->starts)->toHaveCount(12);
    });
});

describe('a day the clocks move back', function () {
    it('offers the ambiguous wall time exactly once', function () {
        $days = ($this->slotsOn)(
            FALL_BACK_SUNDAY,
            ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]),
            zone: new DateTimeZone(MADRID),
        );

        $wallClocks = wallClockStartsOf($days, $this->madrid);

        expect(array_count_values($wallClocks)['02:30'])->toBe(1)
            ->and(array_count_values($wallClocks)['02:00'])->toBe(1);
    });

    it('offers no two starts at the same instant across the repeated hour', function () {
        $days = ($this->slotsOn)(
            FALL_BACK_SUNDAY,
            ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]),
            zone: new DateTimeZone(MADRID),
        );

        $instants = instantsOf($days);

        expect(array_values(array_unique($instants)))->toBe($instants)
            ->and($instants)->toHaveCount(12);
    });

    it('offers each wall time of the day exactly once', function () {
        $days = ($this->slotsOn)(
            FALL_BACK_SUNDAY,
            ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]),
            zone: new DateTimeZone(MADRID),
        );

        $wallClocks = wallClockStartsOf($days, $this->madrid);

        expect(array_values(array_unique($wallClocks)))->toBe($wallClocks);
    });

    it('resolves the repeated wall time to a single real instant', function () {
        $days = ($this->slotsOn)(
            FALL_BACK_SUNDAY,
            ScheduleFixtures::weeklyIntervals([SUNDAY => [['00:00', '06:00']]]),
            zone: new DateTimeZone(MADRID),
        );

        $ambiguous = array_values(array_filter(
            $days[0]->starts,
            fn (DateTimeImmutable $start): bool => $start->setTimezone($this->madrid)->format('H:i') === '02:30',
        ));

        expect($ambiguous)->toHaveCount(1)
            ->and($ambiguous[0]->setTimezone($this->madrid)->format('H:i'))->toBe('02:30');
    });
});

describe('how soon a visitor may book', function () {
    it('offers a start that falls exactly on the lead time', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '11:00']]]),
            rules: new SlotRules(60, null, 30),
            now: '2026-03-10T08:00:00+00:00',
        );

        expect(utcStartsOf($days))->toBe(['09:00', '09:30', '10:00', '10:30']);
    });

    it('offers no start one minute inside the lead time', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '11:00']]]),
            rules: new SlotRules(60, null, 30),
            now: '2026-03-10T08:00:01+00:00',
        );

        expect(utcStartsOf($days))->toBe(['09:30', '10:00', '10:30'])
            ->and(utcStartsOf($days))->not->toContain('09:00');
    });

    it('offers every start of the day when the business asks for no notice at all', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '11:00']]]),
            rules: new SlotRules(0, null, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect(utcStartsOf($days))->toBe(['09:00', '09:30', '10:00', '10:30']);
    });
});

describe('how far ahead a visitor may book', function () {
    it('offers the start that falls exactly on the end of the booking window', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '12:00']]]),
            rules: new SlotRules(0, 120, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect(utcStartsOf($days))->toContain('11:00');
    });

    it('offers no start one minute past the booking window', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '12:00']]]),
            rules: new SlotRules(0, 119, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect(utcStartsOf($days))->not->toContain('11:00')
            ->and(utcStartsOf($days))->toBe(['09:00', '09:30', '10:00', '10:30']);
    });

    it('offers nothing past the booking window', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '12:00']]]),
            rules: new SlotRules(0, 120, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect(utcStartsOf($days))->toBe(['09:00', '09:30', '10:00', '10:30', '11:00'])
            ->and(utcStartsOf($days))->not->toContain('11:30');
    });

    it('does not collapse a sub-day booking window to no reach at all', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '12:00']]]),
            rules: new SlotRules(0, 90, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect(utcStartsOf($days))->toBe(['09:00', '09:30', '10:00', '10:30']);
    });

    it('reaches a year ahead when the business set no booking window', function () {
        $days = ($this->slotsOn)(
            '2026-12-29',
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '10:00']]]),
            rules: new SlotRules(0, null, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect(utcStartsOf($days))->toBe(['09:00', '09:30']);
    });

    it('never reaches past the hard cap however wide the window the business asked for', function () {
        $beyondTheCap = (SlotRules::HARD_CAP_DAYS + 30) * 24 * 60;

        $days = ($this->slotsOn)(
            '2027-03-17',
            ScheduleFixtures::weeklyIntervals([WEDNESDAY => [['09:00', '10:00']]]),
            rules: new SlotRules(0, $beyondTheCap, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect($days[0]->date)->toBe('2027-03-17')
            ->and($days[0]->starts)->toBe([]);
    });

    it('still reaches the last day the hard cap allows', function () {
        $beyondTheCap = (SlotRules::HARD_CAP_DAYS + 30) * 24 * 60;

        $days = ($this->slotsOn)(
            '2027-03-10',
            ScheduleFixtures::weeklyIntervals([WEDNESDAY => [['09:00', '10:00']]]),
            rules: new SlotRules(0, $beyondTheCap, 30),
            now: '2026-03-10T09:00:00+00:00',
        );

        expect(utcStartsOf($days))->toBe(['09:00']);
    });
});

describe('the blocks a booking already consumes', function () {
    beforeEach(function () {
        $this->openHours = ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:25', '13:00']]]);
        $this->bufferedBlock = BookingBlock::lasting(30, 5, 0);
        $this->quarterHourly = new SlotRules(0, null, 15);
    });

    it('offers every start of the shift when nothing is booked yet', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            $this->openHours,
            block: $this->bufferedBlock,
            rules: $this->quarterHourly,
        );

        expect(utcStartsOf($days))->toBe([
            '09:30', '09:45', '10:00', '10:15', '10:30',
            '10:45', '11:00', '11:15', '11:30', '11:45',
            '12:00', '12:15', '12:30',
        ]);
    });

    it('removes the start whose buffer would run into a booking that ends at 11:30', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            $this->openHours,
            booked: [new BookedInterval(
                new DateTimeImmutable('2026-03-10T11:00:00+00:00'),
                new DateTimeImmutable('2026-03-10T11:30:00+00:00'),
            )],
            block: $this->bufferedBlock,
            rules: $this->quarterHourly,
        );

        expect(utcStartsOf($days))->not->toContain('11:30')
            ->and(utcStartsOf($days))->toContain('11:45');
    });

    it('removes every start the booking itself covers', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            $this->openHours,
            booked: [new BookedInterval(
                new DateTimeImmutable('2026-03-10T11:00:00+00:00'),
                new DateTimeImmutable('2026-03-10T11:30:00+00:00'),
            )],
            block: $this->bufferedBlock,
            rules: $this->quarterHourly,
        );

        expect(utcStartsOf($days))->toBe([
            '09:30', '09:45', '10:00', '10:15', '10:30', '11:45', '12:00', '12:15', '12:30',
        ]);
    });

    it('leaves a start alone when the booking ends before its buffer would begin', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            $this->openHours,
            booked: [new BookedInterval(
                new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
                new DateTimeImmutable('2026-03-10T09:25:00+00:00'),
            )],
            block: $this->bufferedBlock,
            rules: $this->quarterHourly,
        );

        expect(utcStartsOf($days))->toContain('09:30');
    });

    it('ignores a booking that falls on another day entirely', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            $this->openHours,
            booked: [new BookedInterval(
                new DateTimeImmutable('2026-03-17T11:00:00+00:00'),
                new DateTimeImmutable('2026-03-17T11:30:00+00:00'),
            )],
            block: $this->bufferedBlock,
            rules: $this->quarterHourly,
        );

        expect(utcStartsOf($days))->toHaveCount(13);
    });
});

describe('a day nobody works', function () {
    it('returns every date of the range, even the ones with no start at all', function () {
        $days = ($this->slotsOn)(
            '2026-03-09',
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '10:00']]]),
            until: '2026-03-13',
        );

        expect(array_column($days, 'date'))->toBe([
            '2026-03-09', '2026-03-10', '2026-03-11', '2026-03-12', '2026-03-13',
        ])
            ->and($days[0]->starts)->toBe([])
            ->and($days[1]->starts)->toHaveCount(2)
            ->and($days[2]->starts)->toBe([]);
    });

    it('returns a day with no start when the business and the staff member share no hour', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '12:00']]]),
            ScheduleFixtures::weeklyIntervals([WEDNESDAY => [['09:00', '12:00']]], ownerId: ScheduleFixtures::STAFF_ID),
        );

        expect($days)->toHaveCount(1)
            ->and($days[0]->date)->toBe(PLAIN_TUESDAY)
            ->and($days[0]->starts)->toBe([]);
    });

    it('returns every date with no start when neither side opens at all', function () {
        $days = ($this->slotsOn)(
            '2026-03-09',
            WeeklyIntervals::none(),
            until: '2026-03-11',
        );

        expect($days)->toHaveCount(3)
            ->and(array_map(static fn (AvailableDay $day): array => $day->starts, $days))
            ->toBe([[], [], []]);
    });

    it('offers only the hours the business and the staff member both keep', function () {
        $days = ($this->slotsOn)(
            PLAIN_TUESDAY,
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['09:00', '14:00']]]),
            ScheduleFixtures::weeklyIntervals([TUESDAY => [['10:00', '11:00']]], ownerId: ScheduleFixtures::STAFF_ID),
        );

        expect(utcStartsOf($days))->toBe(['10:00', '10:30']);
    });
});
