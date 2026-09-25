<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\AvailableDayData;
use App\Domains\Availability\Application\Dtos\SlotQuery;
use App\Domains\Availability\Application\Services\AvailabilityBoard;
use App\Domains\Availability\Contracts\BookableServices;
use App\Domains\Availability\Contracts\BookedIntervals;
use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\ExternalBusyIntervals;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Exceptions\AvailabilityRangeTooWide;
use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\Exceptions\InvalidAvailabilityRange;
use App\Domains\Availability\Exceptions\InvalidSlotQuery;
use App\Domains\Availability\Exceptions\StaffMemberNotBookable;
use App\Domains\Availability\Services\SlotCalculator;
use App\Domains\Availability\ValueObjects\BookableService;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\SlotRules;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

const BOARD_SERVICE_ID = '01930000-0000-7000-8000-0000000000e1';

const BOARD_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000a1';

const BOARD_TUESDAY = 2;

const BOARD_WEDNESDAY = 3;

const BOARD_SUNDAY = 7;

const BOARD_ZONE = 'Europe/Madrid';

beforeEach(function () {
    $this->services = Mockery::mock(BookableServices::class);
    $this->schedules = Mockery::mock(StaffSchedules::class);
    $this->bookings = Mockery::mock(BookedIntervals::class);
    $this->externalBusy = Mockery::mock(ExternalBusyIntervals::class);
    $this->rules = Mockery::mock(BookingRules::class);
    $this->businessClock = Mockery::mock(BusinessClock::class);
    $this->clock = new FakeClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $this->board = new AvailabilityBoard(
        $this->services,
        $this->schedules,
        $this->bookings,
        $this->externalBusy,
        $this->rules,
        $this->businessClock,
        new SlotCalculator,
        $this->clock,
    );

    $this->openFor = function (
        string $timezone = 'UTC',
        ?WeeklyIntervals $businessHours = null,
        ?WeeklyIntervals $staffHours = null,
        array $booked = [],
        ?SlotRules $slotRules = null,
        array $staffIds = [ScheduleFixtures::STAFF_ID],
        array $externallyBusy = [],
    ): void {
        $this->businessClock->shouldReceive('timezoneOf')->andReturn($timezone);
        $this->schedules->shouldReceive('forBusiness')
            ->andReturn($businessHours ?? ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['09:00', '11:00']]]));
        $this->schedules->shouldReceive('forStaffMember')
            ->andReturn($staffHours ?? WeeklyIntervals::none());
        $this->bookings->shouldReceive('forStaffBetween')->andReturn($booked);
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn($externallyBusy);
        $this->rules->shouldReceive('forBusiness')->andReturn($slotRules ?? new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')->andReturn(
            new BookableService(BOARD_SERVICE_ID, 30, 0, $staffIds),
        );
    };

    $this->query = fn (?string $excludingAppointmentId = null): SlotQuery => new SlotQuery(
        serviceId: BOARD_SERVICE_ID,
        staffId: ScheduleFixtures::STAFF_ID,
        from: '2026-03-10',
        to: '2026-03-10',
        excludingAppointmentId: $excludingAppointmentId,
    );

    $this->read = fn (?SlotQuery $query = null): array => $this->board->forBusiness(
        FakeBusinessContext::BUSINESS_ID,
        $query ?? ($this->query)(),
    );
});

describe('reading the days a visitor may book', function () {
    it('answers with a day per date, carrying the starts the calculator worked out', function () {
        ($this->openFor)();

        $days = ($this->read)();

        expect($days)->toHaveCount(1)
            ->and($days[0])->toBeInstanceOf(AvailableDayData::class)
            ->and($days[0]->date)->toBe('2026-03-10')
            ->and(array_map(
                static fn (DateTimeImmutable $start): string => $start->format('H:i'),
                $days[0]->starts,
            ))->toBe(['09:00', '09:30', '10:00', '10:30']);
    });

    it('asks every port about the business it was given, never about another', function () {
        $asked = [];
        $record = function (string $businessId) use (&$asked): bool {
            $asked[] = $businessId;

            return true;
        };

        $this->businessClock->shouldReceive('timezoneOf')->once()->with(Mockery::on($record))->andReturn('UTC');
        $this->schedules->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn(WeeklyIntervals::none());
        $this->schedules->shouldReceive('forStaffMember')->once()->andReturn(WeeklyIntervals::none());
        $this->bookings->shouldReceive('forStaffBetween')->once()
            ->with(Mockery::on($record), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn([]);
        $this->externalBusy->shouldReceive('forStaffBetween')->once()
            ->with(Mockery::on($record), Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn([]);
        $this->rules->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')->once()
            ->with(Mockery::on($record), BOARD_SERVICE_ID)
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));

        ($this->read)();

        expect($asked)->toBe(array_fill(0, 6, FakeBusinessContext::BUSINESS_ID));
    });

    it('reads the hours of the staff member the query names', function () {
        $this->businessClock->shouldReceive('timezoneOf')->andReturn('UTC');
        $this->schedules->shouldReceive('forBusiness')->andReturn(WeeklyIntervals::none());
        $this->schedules->shouldReceive('forStaffMember')->once()
            ->with(ScheduleFixtures::STAFF_ID)
            ->andReturn(WeeklyIntervals::none());
        $this->bookings->shouldReceive('forStaffBetween')->andReturn([]);
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn([]);
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));

        expect(($this->read)()[0]->starts)->toBe([]);
    });
});

describe('the hours a staff member works', function () {
    it('offers a staff member with no rules of their own the business hours', function () {
        ($this->openFor)(
            businessHours: ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['13:00', '14:30']]]),
            staffHours: WeeklyIntervals::none(),
        );

        expect(array_map(
            static fn (DateTimeImmutable $start): string => $start->format('H:i'),
            ($this->read)()[0]->starts,
        ))->toBe(['13:00', '13:30', '14:00']);
    });

    it('offers a slot in staff hours that start before the business opens', function () {
        ($this->openFor)(
            businessHours: ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['09:00', '11:00']]]),
            staffHours: ScheduleFixtures::weeklyIntervals(
                [BOARD_TUESDAY => [['08:00', '09:00']]],
                ScheduleOwnerType::StaffMember,
                ScheduleFixtures::STAFF_ID,
            ),
        );

        expect(array_map(
            static fn (DateTimeImmutable $start): string => $start->format('H:i'),
            ($this->read)()[0]->starts,
        ))->toBe(['08:00', '08:30']);
    });

    it('offers a staff member their own hours in full, even past business closing time', function () {
        ($this->openFor)(
            businessHours: ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['09:00', '11:00']]]),
            staffHours: ScheduleFixtures::weeklyIntervals(
                [BOARD_TUESDAY => [['10:00', '12:00']]],
                ScheduleOwnerType::StaffMember,
                ScheduleFixtures::STAFF_ID,
            ),
        );

        expect(array_map(
            static fn (DateTimeImmutable $start): string => $start->format('H:i'),
            ($this->read)()[0]->starts,
        ))->toBe(['10:00', '10:30', '11:00', '11:30']);
    });

    it('offers nothing on a weekday the staff member left out, even when the business is open', function () {
        ($this->openFor)(
            businessHours: ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['09:00', '11:00']]]),
            staffHours: ScheduleFixtures::weeklyIntervals(
                [BOARD_WEDNESDAY => [['09:00', '11:00']]],
                ScheduleOwnerType::StaffMember,
                ScheduleFixtures::STAFF_ID,
            ),
        );

        expect(($this->read)()[0]->starts)->toBe([]);
    });
});

describe('what a read is allowed to touch', function () {
    it('never writes through the booking policy port, because reading availability changes nothing', function () {
        ($this->openFor)();

        foreach (['save', 'store', 'update', 'replace', 'create'] as $write) {
            $this->rules->shouldNotReceive($write);
        }

        ($this->read)();

        expect(array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(BookingRules::class))->getMethods(),
        ))->toBe(['forBusiness']);
    });

    it('declares a booking policy port that hands a value back rather than returning void', function () {
        $method = new ReflectionMethod(BookingRules::class, 'forBusiness');

        expect((string) $method->getReturnType())->toBe(SlotRules::class)
            ->and((string) $method->getReturnType())->not->toBe('void');
    });

    it('asks the booking policy for the rules exactly once per read', function () {
        $this->businessClock->shouldReceive('timezoneOf')->andReturn('UTC');
        $this->schedules->shouldReceive('forBusiness')->andReturn(WeeklyIntervals::none());
        $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());
        $this->bookings->shouldReceive('forStaffBetween')->andReturn([]);
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn([]);
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));
        $this->rules->shouldReceive('forBusiness')->once()->andReturn(new SlotRules(0, null, 30));

        ($this->read)();
    });

    it('writes nothing through any port it holds', function () {
        ($this->openFor)();

        $writeVerbs = ['save', 'store', 'create', 'update', 'delete', 'remove', 'replace', 'attach'];
        $offenders = [];

        foreach ([BookableServices::class, StaffSchedules::class, BookedIntervals::class, ExternalBusyIntervals::class, BookingRules::class, BusinessClock::class] as $port) {
            foreach ((new ReflectionClass($port))->getMethods() as $method) {
                $writes = array_filter(
                    $writeVerbs,
                    static fn (string $verb): bool => str_starts_with(strtolower($method->getName()), $verb),
                );

                if ($writes !== [] || (string) $method->getReturnType() === 'void') {
                    $offenders[] = $port.'::'.$method->getName().'()';
                }
            }
        }

        expect($offenders)->toBe([])
            ->and(($this->read)())->toHaveCount(1);
    });
});

describe('the appointment being moved', function () {
    it('excludes the appointment under reschedule from the blocks it must avoid', function () {
        $excluded = null;

        $this->businessClock->shouldReceive('timezoneOf')->andReturn('UTC');
        $this->schedules->shouldReceive('forBusiness')
            ->andReturn(ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['09:00', '11:00']]]));
        $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn([]);
        $this->bookings->shouldReceive('forStaffBetween')->once()
            ->with(
                FakeBusinessContext::BUSINESS_ID,
                ScheduleFixtures::STAFF_ID,
                Mockery::type(DateTimeImmutable::class),
                Mockery::type(DateTimeImmutable::class),
                Mockery::capture($excluded),
            )
            ->andReturn([]);

        ($this->read)(($this->query)(BOARD_APPOINTMENT_ID));

        expect($excluded)->toBe(BOARD_APPOINTMENT_ID);
    });

    it('excludes nothing on an ordinary read, so every booking still blocks', function () {
        $excluded = 'never touched';

        $this->businessClock->shouldReceive('timezoneOf')->andReturn('UTC');
        $this->schedules->shouldReceive('forBusiness')
            ->andReturn(ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['09:00', '11:00']]]));
        $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn([]);
        $this->bookings->shouldReceive('forStaffBetween')->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::type(DateTimeImmutable::class),
                Mockery::type(DateTimeImmutable::class),
                Mockery::capture($excluded),
            )
            ->andReturn([]);

        ($this->read)();

        expect($excluded)->toBeNull();
    });

    it('offers back the slot the excluded appointment was holding', function () {
        ($this->openFor)();

        $withoutExclusion = ($this->read)();

        expect(array_map(
            static fn (DateTimeImmutable $start): string => $start->format('H:i'),
            $withoutExclusion[0]->starts,
        ))->toContain('10:00');
    });

    it('keeps blocking a slot another appointment holds, even while one is excluded', function () {
        ($this->openFor)(booked: [new BookedInterval(
            new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
            new DateTimeImmutable('2026-03-10T10:30:00+00:00'),
        )]);

        $starts = array_map(
            static fn (DateTimeImmutable $start): string => $start->format('H:i'),
            ($this->read)(($this->query)(BOARD_APPOINTMENT_ID))[0]->starts,
        );

        expect($starts)->not->toContain('10:00')
            ->and($starts)->toContain('09:00');
    });

    it('carries the exclusion through as an appointment uuid, never an internal key', function () {
        $excluded = null;

        $this->businessClock->shouldReceive('timezoneOf')->andReturn('UTC');
        $this->schedules->shouldReceive('forBusiness')->andReturn(WeeklyIntervals::none());
        $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn([]);
        $this->bookings->shouldReceive('forStaffBetween')->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::capture($excluded))
            ->andReturn([]);

        ($this->read)(($this->query)(BOARD_APPOINTMENT_ID));

        expect($excluded)->toBeString()
            ->and(is_numeric($excluded))->toBeFalse();
    });
});

describe('a query the board refuses', function () {
    it('validates the query before it asks any port a thing', function () {
        foreach ([$this->businessClock, $this->schedules, $this->bookings, $this->externalBusy, $this->rules, $this->services] as $port) {
            $port->shouldNotReceive('timezoneOf', 'forBusiness', 'forStaffMember', 'forStaffBetween', 'describe');
        }

        expect(fn () => $this->board->forBusiness(
            FakeBusinessContext::BUSINESS_ID,
            new SlotQuery('not-a-uuid', ScheduleFixtures::STAFF_ID, '2026-03-10', '2026-03-10'),
        ))->toThrow(InvalidSlotQuery::class);
    });

    it('refuses a malformed staff id', function () {
        expect(fn () => $this->board->forBusiness(
            FakeBusinessContext::BUSINESS_ID,
            new SlotQuery(BOARD_SERVICE_ID, 'not-a-uuid', '2026-03-10', '2026-03-10'),
        ))->toThrow(InvalidSlotQuery::class);
    });

    it('refuses an inverted range', function () {
        expect(fn () => $this->board->forBusiness(
            FakeBusinessContext::BUSINESS_ID,
            new SlotQuery(BOARD_SERVICE_ID, ScheduleFixtures::STAFF_ID, '2026-03-20', '2026-03-10'),
        ))->toThrow(InvalidAvailabilityRange::class);
    });

    it('refuses a range wider than the calendar allows', function () {
        expect(fn () => $this->board->forBusiness(
            FakeBusinessContext::BUSINESS_ID,
            new SlotQuery(BOARD_SERVICE_ID, ScheduleFixtures::STAFF_ID, '2026-01-01', '2026-12-31'),
        ))->toThrow(AvailabilityRangeTooWide::class);
    });

    it('lets a missing service out as the refusal the catalogue raised', function () {
        $this->businessClock->shouldReceive('timezoneOf')->andReturn('UTC');
        $this->schedules->shouldReceive('forBusiness')->andReturn(WeeklyIntervals::none());
        $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());
        $this->bookings->shouldReceive('forStaffBetween')->andReturn([]);
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn([]);
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')
            ->andThrow(BookableServiceNotFound::withId(BOARD_SERVICE_ID));

        expect(fn () => ($this->read)())->toThrow(BookableServiceNotFound::class);
    });

    it('lets a staff member who does not perform the service out as a refusal', function () {
        ($this->openFor)(staffIds: [ScheduleFixtures::OTHER_STAFF_ID]);

        expect(fn () => ($this->read)())->toThrow(StaffMemberNotBookable::class);
    });
});

describe('the timezone the day is measured in', function () {
    it('reads the wall clock hours in the timezone the business keeps', function () {
        ($this->openFor)(timezone: 'Europe/Madrid');

        expect(array_map(
            static fn (DateTimeImmutable $start): string => $start->format('H:i'),
            ($this->read)()[0]->starts,
        ))->toBe(['08:00', '08:30', '09:00', '09:30']);
    });

    it('hands the calculator the range boundaries in the business timezone', function () {
        $from = null;
        $to = null;

        $this->businessClock->shouldReceive('timezoneOf')->andReturn('Europe/Madrid');
        $this->schedules->shouldReceive('forBusiness')->andReturn(WeeklyIntervals::none());
        $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));
        $this->externalBusy->shouldReceive('forStaffBetween')->andReturn([]);
        $this->bookings->shouldReceive('forStaffBetween')->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::capture($from),
                Mockery::capture($to),
                Mockery::any(),
            )
            ->andReturn([]);

        ($this->read)();

        expect($from->format(DATE_ATOM))->toBe('2026-03-10T00:00:00+01:00')
            ->and($to->format(DATE_ATOM))->toBe('2026-03-11T00:00:00+01:00');
    });
});

describe('time a connected calendar holds outside Mizita', function () {
    beforeEach(function () {
        $this->localStarts = static fn (array $days): array => array_map(
            static fn (DateTimeImmutable $start): string => $start->setTimezone(new DateTimeZone(BOARD_ZONE))->format('H:i'),
            $days[0]->starts,
        );

        $this->interval = static fn (string $startsAt, string $endsAt): BookedInterval => new BookedInterval(
            new DateTimeImmutable($startsAt),
            new DateTimeImmutable($endsAt),
        );

        $this->openInMadrid = function (array $booked = [], array $externallyBusy = []): void {
            ($this->openFor)(
                timezone: BOARD_ZONE,
                booked: $booked,
                externallyBusy: $externallyBusy,
            );
        };
    });

    it('removes every slot an external busy interval overlaps', function (string $startsAt, string $endsAt, array $expected) {
        ($this->openInMadrid)(externallyBusy: [($this->interval)($startsAt, $endsAt)]);

        expect(($this->localStarts)(($this->read)()))->toBe($expected);
    })->with([
        'a single slot' => ['2026-03-10T10:00:00+01:00', '2026-03-10T10:30:00+01:00', ['09:00', '09:30', '10:30']],
        'a span across three slots' => ['2026-03-10T09:15:00+01:00', '2026-03-10T10:15:00+01:00', ['10:30']],
        'an event reported in UTC' => ['2026-03-10T08:00:00+00:00', '2026-03-10T08:30:00+00:00', ['09:30', '10:00', '10:30']],
    ]);

    it('keeps a slot that an external interval only touches at its edge', function () {
        ($this->openInMadrid)(externallyBusy: [($this->interval)('2026-03-10T08:30:00+01:00', '2026-03-10T09:00:00+01:00')]);

        expect(($this->localStarts)(($this->read)()))->toBe(['09:00', '09:30', '10:00', '10:30']);
    });

    it('leaves the board exactly as bookings alone shape it when the calendar is free', function () {
        ($this->openInMadrid)(
            booked: [($this->interval)('2026-03-10T09:30:00+01:00', '2026-03-10T10:00:00+01:00')],
            externallyBusy: [],
        );

        expect(($this->localStarts)(($this->read)()))->toBe(['09:00', '10:00', '10:30']);
    });

    it('applies booked and externally busy intervals together', function () {
        ($this->openInMadrid)(
            booked: [($this->interval)('2026-03-10T09:00:00+01:00', '2026-03-10T09:30:00+01:00')],
            externallyBusy: [($this->interval)('2026-03-10T10:30:00+01:00', '2026-03-10T11:00:00+01:00')],
        );

        expect(($this->localStarts)(($this->read)()))->toBe(['09:30', '10:00']);
    });
});

describe('the range the connected calendar is asked about', function () {
    beforeEach(function () {
        $this->bookedRange = [];
        $this->externalArguments = [];

        $this->businessClock->shouldReceive('timezoneOf')->andReturn(BOARD_ZONE);
        $this->schedules->shouldReceive('forBusiness')->andReturn(WeeklyIntervals::none());
        $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));
        $this->bookings->shouldReceive('forStaffBetween')->once()
            ->andReturnUsing(function (string $businessId, string $staffId, DateTimeImmutable $from, DateTimeImmutable $to): array {
                $this->bookedRange = [$from, $to];

                return [];
            });
        $this->externalBusy->shouldReceive('forStaffBetween')->once()
            ->andReturnUsing(function (...$arguments): array {
                $this->externalArguments = $arguments;

                return [];
            });

        $this->readDay = fn (string $date, ?string $excludingAppointmentId = null): array => $this->board->forBusiness(
            FakeBusinessContext::BUSINESS_ID,
            new SlotQuery(BOARD_SERVICE_ID, ScheduleFixtures::STAFF_ID, $date, $date, $excludingAppointmentId),
        );
    });

    it('asks about the business and staff member the query names', function () {
        ($this->readDay)('2026-03-10');

        expect($this->externalArguments[0])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->externalArguments[1])->toBe(ScheduleFixtures::STAFF_ID);
    });

    it('asks for the same instants the bookings are read over', function () {
        ($this->readDay)('2026-03-10');

        expect($this->externalArguments[2])->toEqual($this->bookedRange[0])
            ->and($this->externalArguments[3])->toEqual($this->bookedRange[1]);
    });

    it('measures the range from local midnight to local midnight in the business timezone', function () {
        ($this->readDay)('2026-03-10');

        expect($this->externalArguments[2]->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM))
            ->toBe('2026-03-09T23:00:00+00:00')
            ->and($this->externalArguments[3]->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM))
            ->toBe('2026-03-10T23:00:00+00:00');
    });

    it('follows the offset change across a daylight saving day', function (string $date, string $from, string $to, int $hours) {
        ($this->readDay)($date);

        [, , $externalFrom, $externalTo] = $this->externalArguments;

        expect($externalFrom->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM))->toBe($from)
            ->and($externalTo->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM))->toBe($to)
            ->and(($externalTo->getTimestamp() - $externalFrom->getTimestamp()) / 3600)->toBe($hours);
    })->with([
        'spring forward' => ['2026-03-29', '2026-03-28T23:00:00+00:00', '2026-03-29T22:00:00+00:00', 23],
        'fall back' => ['2026-10-25', '2026-10-24T22:00:00+00:00', '2026-10-25T23:00:00+00:00', 25],
    ]);

    it('never hands the calendar the appointment being moved', function () {
        ($this->readDay)('2026-03-10', BOARD_APPOINTMENT_ID);

        expect($this->externalArguments)->toHaveCount(4)
            ->and($this->externalArguments)->not->toContain(BOARD_APPOINTMENT_ID);
    });
});

describe('an external event while an appointment is being moved', function () {
    it('still blocks the old time when the calendar is busy there', function () {
        ($this->openFor)(
            timezone: BOARD_ZONE,
            booked: [],
            externallyBusy: [new BookedInterval(
                new DateTimeImmutable('2026-03-10T10:00:00+01:00'),
                new DateTimeImmutable('2026-03-10T10:30:00+01:00'),
            )],
        );

        $starts = array_map(
            static fn (DateTimeImmutable $start): string => $start->setTimezone(new DateTimeZone(BOARD_ZONE))->format('H:i'),
            ($this->read)(($this->query)(BOARD_APPOINTMENT_ID))[0]->starts,
        );

        expect($starts)->toBe(['09:00', '09:30', '10:30']);
    });

    it('blocks an externally busy slot across a daylight saving change', function (string $date, int $weekday, string $startsAt, string $endsAt) {
        ($this->openFor)(
            timezone: BOARD_ZONE,
            businessHours: ScheduleFixtures::weeklyIntervals([$weekday => [['09:00', '11:00']]]),
            externallyBusy: [new BookedInterval(new DateTimeImmutable($startsAt), new DateTimeImmutable($endsAt))],
        );

        $starts = array_map(
            static fn (DateTimeImmutable $start): string => $start->setTimezone(new DateTimeZone(BOARD_ZONE))->format('H:i'),
            $this->board->forBusiness(
                FakeBusinessContext::BUSINESS_ID,
                new SlotQuery(BOARD_SERVICE_ID, ScheduleFixtures::STAFF_ID, $date, $date, BOARD_APPOINTMENT_ID),
            )[0]->starts,
        );

        expect($starts)->toBe(['09:00', '09:30', '10:30']);
    })->with([
        'spring forward' => ['2026-03-29', BOARD_SUNDAY, '2026-03-29T08:00:00+00:00', '2026-03-29T08:30:00+00:00'],
        'fall back' => ['2026-10-25', BOARD_SUNDAY, '2026-10-25T09:00:00+00:00', '2026-10-25T09:30:00+00:00'],
    ]);
});
