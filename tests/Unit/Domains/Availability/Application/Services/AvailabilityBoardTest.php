<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\AvailableDayData;
use App\Domains\Availability\Application\Dtos\SlotQuery;
use App\Domains\Availability\Application\Services\AvailabilityBoard;
use App\Domains\Availability\Contracts\BookableServices;
use App\Domains\Availability\Contracts\BookedIntervals;
use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Exceptions\AvailabilityRangeTooWide;
use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\Exceptions\InvalidAvailabilityRange;
use App\Domains\Availability\Exceptions\InvalidSlotQuery;
use App\Domains\Availability\Exceptions\StaffMemberNotBookable;
use App\Domains\Availability\Services\SlotCalculator;
use App\Domains\Availability\ValueObjects\BookableService;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Availability\ValueObjects\SlotRules;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

const BOARD_SERVICE_ID = '01930000-0000-7000-8000-0000000000e1';

const BOARD_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000a1';

const BOARD_TUESDAY = 2;

beforeEach(function () {
    $this->services = Mockery::mock(BookableServices::class);
    $this->schedules = Mockery::mock(StaffSchedules::class);
    $this->bookings = Mockery::mock(BookedIntervals::class);
    $this->rules = Mockery::mock(BookingRules::class);
    $this->businessClock = Mockery::mock(BusinessClock::class);
    $this->clock = new FakeClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $this->board = new AvailabilityBoard(
        $this->services,
        $this->schedules,
        $this->bookings,
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
    ): void {
        $this->businessClock->shouldReceive('timezoneOf')->andReturn($timezone);
        $this->schedules->shouldReceive('forBusiness')
            ->andReturn($businessHours ?? ScheduleFixtures::weeklyIntervals([BOARD_TUESDAY => [['09:00', '11:00']]]));
        $this->schedules->shouldReceive('forStaffMember')
            ->andReturn($staffHours ?? WeeklyIntervals::none());
        $this->bookings->shouldReceive('forStaffBetween')->andReturn($booked);
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
        $this->rules->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn(new SlotRules(0, null, 30));
        $this->services->shouldReceive('describe')->once()
            ->with(Mockery::on($record), BOARD_SERVICE_ID)
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));

        ($this->read)();

        expect($asked)->toBe(array_fill(0, 5, FakeBusinessContext::BUSINESS_ID));
    });

    it('lets a staff member with no hours of their own inherit the business hours', function () {
        ($this->openFor)(staffHours: WeeklyIntervals::none());

        expect(($this->read)()[0]->starts)->toHaveCount(4);
    });

    it('holds a staff member with hours of their own to the intersection', function () {
        ($this->openFor)(staffHours: ScheduleFixtures::weeklyIntervals(
            [BOARD_TUESDAY => [['10:00', '11:00']]],
            ownerId: ScheduleFixtures::STAFF_ID,
        ));

        expect(array_map(
            static fn (DateTimeImmutable $start): string => $start->format('H:i'),
            ($this->read)()[0]->starts,
        ))->toBe(['10:00', '10:30']);
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
        $this->services->shouldReceive('describe')
            ->andReturn(new BookableService(BOARD_SERVICE_ID, 30, 0, [ScheduleFixtures::STAFF_ID]));
        $this->rules->shouldReceive('forBusiness')->once()->andReturn(new SlotRules(0, null, 30));

        ($this->read)();
    });

    it('writes nothing through any port it holds', function () {
        ($this->openFor)();

        $writeVerbs = ['save', 'store', 'create', 'update', 'delete', 'remove', 'replace', 'attach'];
        $offenders = [];

        foreach ([BookableServices::class, StaffSchedules::class, BookedIntervals::class, BookingRules::class, BusinessClock::class] as $port) {
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
        foreach ([$this->businessClock, $this->schedules, $this->bookings, $this->rules, $this->services] as $port) {
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
