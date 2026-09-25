<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\MyScheduleEntryInput;
use App\Domains\Availability\Application\Dtos\ReplaceStaffMemberScheduleInput;
use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\Exceptions\ScheduleNotSubmitted;
use App\Domains\Availability\Exceptions\StaffMemberNotFound;
use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\ScheduleFixtures;

beforeEach(function () {
    $this->input = fn (array $payload, string $staffMemberId = ScheduleFixtures::STAFF_ID) => ReplaceStaffMemberScheduleInput::fromRequest($payload, $staffMemberId);
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

    it('takes the staff member from the route, never from the body', function () {
        $input = ($this->input)(['schedule' => [], 'staff_member_id' => ScheduleFixtures::OTHER_STAFF_ID]);

        expect($input->staffMemberId)->toBe(ScheduleFixtures::STAFF_ID);
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

    it('reads a wrongly typed value as nothing rather than casting it', function (array $entry, string $field, mixed $expected) {
        expect(($this->input)(['schedule' => [$entry]])->entries[0]->{$field})->toBe($expected);
    })->with([
        'weekday as a name' => [['weekday' => 'monday'], 'weekday', 0],
        'weekday as false' => [['weekday' => false], 'weekday', 0],
        'weekday as a list' => [['weekday' => [1]], 'weekday', 0],
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

    it('accepts an empty week, which sends the staff member back to the business hours', function () {
        expect(fn () => ($this->input)(['schedule' => []])->validate())->not->toThrow(Throwable::class);
    });

    it('turns a body with every key missing into a domain failure rather than a PHP error', function () {
        expect(fn () => ($this->input)([])->validate())->toThrow(ScheduleNotSubmitted::class);
    });

    it('names the staff member whose schedule never arrived', function () {
        try {
            ($this->input)([])->validate();
        } catch (ScheduleNotSubmitted $failure) {
            expect($failure->errorCode())->toBe('schedule_not_submitted')
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid)
                ->and($failure->getMessage())->toContain(ScheduleFixtures::STAFF_ID);

            return;
        }

        $this->fail('validate() accepted a payload with no schedule.');
    });

    it('rejects a payload the form request would have rejected', function (array $payload, string $exception) {
        expect(fn () => ($this->input)($payload)->validate())->toThrow($exception);
    })->with('rejected staff member schedule payloads');

    it('raises an invalid domain failure for every payload it rejects', function (array $payload) {
        try {
            ($this->input)($payload)->validate();
        } catch (Throwable $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        $this->fail('validate() accepted a payload it should have rejected.');
    })->with('rejected staff member schedule payloads');

    it('leaves inversion and overlap to the rules, since a payload alone cannot tell', function () {
        $input = ($this->input)(['schedule' => [
            ['weekday' => 1, 'starts_at' => '18:00', 'ends_at' => '09:00'],
            ['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '20:00'],
        ]]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });
});

describe('validating the staff member it names', function () {
    it('reports a staff member id that is not a uuid as not found', function (string $staffMemberId) {
        expect(fn () => ($this->input)(['schedule' => []], $staffMemberId)->validate())
            ->toThrow(StaffMemberNotFound::class);
    })->with('malformed staff member ids for replacing');

    it('refuses with a not found failure the transport can classify', function (string $staffMemberId) {
        try {
            ($this->input)(['schedule' => []], $staffMemberId)->validate();
        } catch (StaffMemberNotFound $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('staff_member_not_found')
                ->and($failure->kind())->toBe(DomainFailureKind::NotFound);

            return;
        }

        $this->fail('validate() accepted a staff member id that is not a uuid.');
    })->with('malformed staff member ids for replacing');

    it('checks the staff member before the schedule', function (array $payload) {
        expect(fn () => ($this->input)($payload, 'not-a-uuid')->validate())
            ->toThrow(StaffMemberNotFound::class);
    })->with([
        'no schedule at all' => [[]],
        'an invalid weekday' => [['schedule' => [['weekday' => 9, 'starts_at' => '09:00', 'ends_at' => '14:00']]]],
        'a malformed time' => [['schedule' => [['weekday' => 1, 'starts_at' => 'nine', 'ends_at' => '14:00']]]],
    ]);
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

dataset('malformed staff member ids for replacing', [
    'empty' => '',
    'whitespace only' => '   ',
    'a row number' => '1',
    'a word' => 'ada',
    'surrounding whitespace' => ' '.ScheduleFixtures::STAFF_ID.' ',
    'a trailing newline' => ScheduleFixtures::STAFF_ID."\n",
    'one character too long' => ScheduleFixtures::STAFF_ID.'0',
]);

dataset('rejected staff member schedule payloads', function () {
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
        'weekday missing' => [['schedule' => [['starts_at' => '09:00', 'ends_at' => '14:00']]], InvalidWeekday::class],
        'fractional weekday' => [['schedule' => [$entry('3.7')]], InvalidWeekday::class],
        'entry is not an object' => [['schedule' => ['monday']], InvalidWeekday::class],
        'start missing' => [['schedule' => [['weekday' => 1, 'ends_at' => '14:00']]], InvalidTimeOfDay::class],
        'end missing' => [['schedule' => [['weekday' => 1, 'starts_at' => '09:00']]], InvalidTimeOfDay::class],
        'whitespace-only end' => [['schedule' => [$entry(1, '09:00', "\t ")]], InvalidTimeOfDay::class],
        'start hour twenty-four' => [['schedule' => [$entry(1, '24:00')]], InvalidTimeOfDay::class],
        'end minute sixty' => [['schedule' => [$entry(1, '09:00', '14:60')]], InvalidTimeOfDay::class],
        'unpadded start hour' => [['schedule' => [$entry(1, '9:00')]], InvalidTimeOfDay::class],
        'end with seconds' => [['schedule' => [$entry(1, '09:00', '14:00:00')]], InvalidTimeOfDay::class],
        'unicode digits' => [['schedule' => [$entry(1, '０９:００')]], InvalidTimeOfDay::class],
        'bad entry after a good one' => [['schedule' => [$entry(), $entry(9)]], InvalidWeekday::class],
    ];
});
