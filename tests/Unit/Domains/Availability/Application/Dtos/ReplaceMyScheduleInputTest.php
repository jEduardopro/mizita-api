<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\MyScheduleEntryInput;
use App\Domains\Availability\Application\Dtos\ReplaceMyScheduleInput;
use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\Exceptions\ScheduleNotSubmitted;
use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

beforeEach(function () {
    $this->accountId = '01930000-0000-7000-8000-0000000000a1';

    $this->input = fn (array $payload) => ReplaceMyScheduleInput::fromRequest($payload, $this->accountId);
});

describe('reading a payload', function () {
    it('assembles one entry per interval the form request would have passed', function () {
        $input = ($this->input)(['schedule' => [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
            ['weekday' => 7, 'starts_at' => '16:30', 'ends_at' => '20:15'],
        ]]);

        expect($input->entries)->toHaveCount(2)
            ->and($input->entries)->each->toBeInstanceOf(MyScheduleEntryInput::class)
            ->and($input->entries[0]->weekday)->toBe(1)
            ->and($input->entries[0]->startsAt)->toBe('09:00')
            ->and($input->entries[0]->endsAt)->toBe('14:00')
            ->and($input->entries[1]->weekday)->toBe(7)
            ->and($input->entries[1]->startsAt)->toBe('16:30')
            ->and($input->entries[1]->endsAt)->toBe('20:15');
    });

    it('takes the account from the authenticated caller, never from the body', function () {
        $input = ($this->input)(['schedule' => [], 'account_id' => '01930000-0000-7000-8000-0000000000a9']);

        expect($input->accountId)->toBe($this->accountId);
    });

    it('keeps an empty schedule apart from a missing one', function () {
        expect(($this->input)(['schedule' => []])->entries)->toBe([])
            ->and(($this->input)([])->entries)->toBeNull();
    });

    it('reads a schedule that is not a list as not submitted', function (mixed $schedule) {
        expect(($this->input)(['schedule' => $schedule])->entries)->toBeNull();
    })->with([
        'null' => [null],
        'a string' => ['monday'],
        'a number' => [1],
        'a boolean' => [true],
    ]);

    it('survives an entry with every key missing', function () {
        $entry = ($this->input)(['schedule' => [[]]])->entries[0];

        expect($entry->weekday)->toBe(0)
            ->and($entry->startsAt)->toBe('')
            ->and($entry->endsAt)->toBe('');
    });

    it('survives an entry that is not an object at all', function () {
        $entry = ($this->input)(['schedule' => ['monday']])->entries[0];

        expect($entry->weekday)->toBe(0)
            ->and($entry->startsAt)->toBe('')
            ->and($entry->endsAt)->toBe('');
    });

    it('reads an integer weekday string as its number', function () {
        expect(($this->input)(['schedule' => [['weekday' => '3']]])->entries[0]->weekday)->toBe(3);
    });

    it('reads a true weekday as Monday, the way the integer rule of the form request accepts it', function () {
        expect(($this->input)(['schedule' => [['weekday' => true]]])->entries[0]->weekday)->toBe(1);
    });

    it('reads a wrongly typed value as nothing rather than casting it', function (array $entry, string $field, mixed $expected) {
        expect(($this->input)(['schedule' => [$entry]])->entries[0]->{$field})->toBe($expected);
    })->with([
        'weekday as a name' => [['weekday' => 'monday'], 'weekday', 0],
        'weekday as false' => [['weekday' => false], 'weekday', 0],
        'weekday as a list' => [['weekday' => [1]], 'weekday', 0],
        'weekday as a fractional string' => [['weekday' => '3.7'], 'weekday', 0],
        'weekday as a fraction' => [['weekday' => 3.7], 'weekday', 0],
        'weekday as a decimal string' => [['weekday' => '3.0'], 'weekday', 0],
        'start as a number' => [['starts_at' => 900], 'startsAt', ''],
        'end as a list' => [['ends_at' => ['14:00']], 'endsAt', ''],
    ]);

    it('renumbers a schedule sent with keys of its own', function () {
        $input = ($this->input)(['schedule' => [
            'monday' => ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
            'tuesday' => ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '14:00'],
        ]]);

        expect(array_keys($input->entries))->toBe([0, 1]);
    });
});

describe('validating itself', function () {
    it('accepts a well formed week', function () {
        $input = ($this->input)(['schedule' => [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
            ['weekday' => 7, 'starts_at' => '00:00', 'ends_at' => '23:59'],
        ]]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('accepts an empty week, which is how a staff member goes back to the business hours', function () {
        expect(fn () => ($this->input)(['schedule' => []])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts the first and last weekday', function (int $weekday) {
        $input = ($this->input)(['schedule' => [['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '14:00']]]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    })->with(['monday' => [1], 'sunday' => [7]]);

    it('turns a body with every key missing into a domain failure rather than a PHP error', function () {
        expect(fn () => ($this->input)([])->validate())->toThrow(ScheduleNotSubmitted::class);
    });

    it('rejects a payload the form request would have rejected', function (array $payload, string $exception) {
        expect(fn () => ($this->input)($payload)->validate())->toThrow($exception);
    })->with('rejected my schedule payloads');

    it('raises a domain failure for every payload it rejects', function (array $payload) {
        try {
            ($this->input)($payload)->validate();
        } catch (Throwable $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        $this->fail('validate() accepted a payload it should have rejected.');
    })->with('rejected my schedule payloads');

    it('names the missing schedule with its own code', function () {
        try {
            ($this->input)([])->validate();
        } catch (ScheduleNotSubmitted $failure) {
            expect($failure->errorCode())->toBe('schedule_not_submitted');

            return;
        }

        $this->fail('validate() accepted a payload with no schedule.');
    });

    it('leaves inversion and overlap to the rules, since a payload alone cannot tell', function () {
        $input = ($this->input)(['schedule' => [
            ['weekday' => 1, 'starts_at' => '18:00', 'ends_at' => '09:00'],
            ['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '20:00'],
        ]]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('rejects a fractional weekday rather than rounding it to a day', function (mixed $weekday) {
        $input = ($this->input)(['schedule' => [['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '14:00']]]);

        expect(fn () => $input->validate())->toThrow(InvalidWeekday::class);
    })->with([
        'as a string' => ['3.7'],
        'as a float' => [3.7],
        'as a decimal string' => ['3.0'],
    ]);

    it('rejects every time not written as strict HH:MM', function (string $time) {
        $input = ($this->input)(['schedule' => [['weekday' => 1, 'starts_at' => $time, 'ends_at' => '23:00']]]);

        expect(fn () => $input->validate())->toThrow(InvalidTimeOfDay::class);
    })->with([
        'an unpadded hour' => ['9:00'],
        'with seconds' => ['09:00:00'],
        'a leading space' => [' 09:00'],
        'a trailing space' => ['09:00 '],
        'a trailing newline' => ["09:00\n"],
        'a dot separator' => ['09.00'],
        'a single-digit minute' => ['09:0'],
        'hour twenty-four' => ['24:00'],
    ]);

    it('names a malformed time with the time-of-day code', function () {
        try {
            ($this->input)(['schedule' => [['weekday' => 1, 'starts_at' => '9:00', 'ends_at' => '14:00']]])->validate();
        } catch (InvalidTimeOfDay $failure) {
            expect($failure->errorCode())->toBe('invalid_time_of_day');

            return;
        }

        $this->fail('validate() accepted an unpadded hour.');
    });
});

describe('turning the entries into intervals', function () {
    it('builds one interval per entry, in the order they were sent', function () {
        $intervals = ($this->input)(['schedule' => [
            ['weekday' => 7, 'starts_at' => '16:30', 'ends_at' => '20:15'],
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '14:00'],
        ]])->intervals();

        expect($intervals)->toHaveCount(2)
            ->and($intervals)->each->toBeInstanceOf(ScheduleInterval::class)
            ->and($intervals[0]->weekday)->toBe(Weekday::Sunday)
            ->and($intervals[0]->startsAt->toString())->toBe('16:30')
            ->and($intervals[0]->endsAt->toString())->toBe('20:15')
            ->and($intervals[1]->weekday)->toBe(Weekday::Monday)
            ->and($intervals[1]->startsAt->toString())->toBe('09:00')
            ->and($intervals[1]->endsAt->toString())->toBe('14:00');
    });

    it('has no intervals for an empty schedule', function () {
        expect(($this->input)(['schedule' => []])->intervals())->toBe([]);
    });

    it('has no intervals for a schedule that was never submitted', function () {
        expect(($this->input)([])->intervals())->toBe([]);
    });
});

dataset('rejected my schedule payloads', function () {
    $entry = fn (mixed $weekday = 1, mixed $startsAt = '09:00', mixed $endsAt = '14:00') => [
        'weekday' => $weekday,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ];

    return [
        'the schedule key is missing' => [[], ScheduleNotSubmitted::class],
        'the schedule is null' => [['schedule' => null], ScheduleNotSubmitted::class],
        'the schedule is a string' => [['schedule' => 'monday'], ScheduleNotSubmitted::class],
        'weekday zero' => [['schedule' => [$entry(0)]], InvalidWeekday::class],
        'weekday eight' => [['schedule' => [$entry(8)]], InvalidWeekday::class],
        'negative weekday' => [['schedule' => [$entry(-1)]], InvalidWeekday::class],
        'weekday missing' => [['schedule' => [['starts_at' => '09:00', 'ends_at' => '14:00']]], InvalidWeekday::class],
        'weekday as a name' => [['schedule' => [$entry('monday')]], InvalidWeekday::class],
        'entry is not an object' => [['schedule' => ['monday']], InvalidWeekday::class],
        'start missing' => [['schedule' => [['weekday' => 1, 'ends_at' => '14:00']]], InvalidTimeOfDay::class],
        'end missing' => [['schedule' => [['weekday' => 1, 'starts_at' => '09:00']]], InvalidTimeOfDay::class],
        'empty start' => [['schedule' => [$entry(1, '')]], InvalidTimeOfDay::class],
        'whitespace-only end' => [['schedule' => [$entry(1, '09:00', "\t ")]], InvalidTimeOfDay::class],
        'malformed start' => [['schedule' => [$entry(1, 'nine')]], InvalidTimeOfDay::class],
        'start hour twenty-four' => [['schedule' => [$entry(1, '24:00')]], InvalidTimeOfDay::class],
        'end minute sixty' => [['schedule' => [$entry(1, '09:00', '14:60')]], InvalidTimeOfDay::class],
        'end as a number' => [['schedule' => [$entry(1, '09:00', 1400)]], InvalidTimeOfDay::class],
        'unicode digits' => [['schedule' => [$entry(1, '０９:００')]], InvalidTimeOfDay::class],
        'fractional weekday' => [['schedule' => [$entry('3.7')]], InvalidWeekday::class],
        'unpadded start hour' => [['schedule' => [$entry(1, '9:00')]], InvalidTimeOfDay::class],
        'end with seconds' => [['schedule' => [$entry(1, '09:00', '14:00:00')]], InvalidTimeOfDay::class],
        'padded start' => [['schedule' => [$entry(1, ' 09:00 ')]], InvalidTimeOfDay::class],
        'bad entry after a good one' => [['schedule' => [$entry(), $entry(9)]], InvalidWeekday::class],
    ];
});
