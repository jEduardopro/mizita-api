<?php

declare(strict_types=1);

use App\Domains\Availability\Infrastructure\Gateways\IntegrationsExternalBusyIntervals;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Integrations\Application\UseCases\ListCalendarBusyIntervals;
use App\Domains\Integrations\Contracts\BusinessProfiles;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarEventFeed;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\ExternalEventAvailability;
use App\Domains\Integrations\ValueObjects\ExternalEventOrigin;
use App\Domains\Integrations\ValueObjects\TimedEventSpan;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->staffMemberId = '01930000-0000-7000-8000-0000000000a1';
    $this->from = new DateTimeImmutable('2026-10-05T00:00:00+00:00');
    $this->to = new DateTimeImmutable('2026-10-06T00:00:00+00:00');

    $this->connection = CalendarConnection::restore(
        id: '01930000-0000-7000-8000-0000000000c1',
        businessId: FakeBusinessContext::BUSINESS_ID,
        staffMemberId: $this->staffMemberId,
        provider: CalendarProvider::Google,
        accountEmail: 'ada@example.com',
        externalCalendarId: 'mizita-calendar',
        status: ConnectionStatus::Connected,
        connectedAt: new DateTimeImmutable('2026-09-01T09:00:00+00:00'),
    );

    $this->connectionLookups = [];
    $this->feedWindows = [];
    $this->events = [];
    $this->storedConnection = $this->connection;

    $connections = Mockery::mock(CalendarConnectionRepository::class);
    $connections->shouldReceive('findForStaffMember')->andReturnUsing(
        function (string $businessId, string $staffMemberId, CalendarProvider $provider): ?CalendarConnection {
            $this->connectionLookups[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId];

            return $this->storedConnection;
        },
    );

    $this->businesses = Mockery::mock(BusinessProfiles::class);
    $this->businesses->shouldReceive('profileOf')->andReturn(new BusinessCalendarProfile('Barbería Ñandú', 'Europe/Madrid'))->byDefault();

    $feed = Mockery::mock(CalendarEventFeed::class);
    $feed->shouldReceive('eventsBetween')->andReturnUsing(
        function (CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): array {
            $this->feedWindows[] = ['connection' => $connection, 'from' => $from, 'to' => $to];

            return $this->events;
        },
    );

    $this->gateway = new IntegrationsExternalBusyIntervals(
        new ListCalendarBusyIntervals($connections, $this->businesses, $feed),
    );

    $this->busyEvent = static fn (string $startsAt, string $endsAt): ExternalCalendarEvent => new ExternalCalendarEvent(
        new TimedEventSpan(new DateTimeImmutable($startsAt), new DateTimeImmutable($endsAt)),
        ExternalEventOrigin::AddedByHand,
        ExternalEventAvailability::Busy,
    );
});

describe('a calendar with busy time', function () {
    beforeEach(function () {
        $this->events = [
            ($this->busyEvent)('2026-10-05T09:00:00+02:00', '2026-10-05T10:30:00+02:00'),
            ($this->busyEvent)('2026-10-05T16:15:00+00:00', '2026-10-05T17:00:00+00:00'),
        ];
    });

    it('returns one booked interval per busy interval', function () {
        $intervals = $this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to);

        expect($intervals)->toHaveCount(2)
            ->each->toBeInstanceOf(BookedInterval::class);
    });

    it('keeps the instants of every busy interval, in order', function () {
        $intervals = $this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to);

        expect(array_map(
            static fn (BookedInterval $interval): array => [$interval->startsAt->getTimestamp(), $interval->endsAt->getTimestamp()],
            $intervals,
        ))->toBe([
            [(new DateTimeImmutable('2026-10-05T07:00:00+00:00'))->getTimestamp(), (new DateTimeImmutable('2026-10-05T08:30:00+00:00'))->getTimestamp()],
            [(new DateTimeImmutable('2026-10-05T16:15:00+00:00'))->getTimestamp(), (new DateTimeImmutable('2026-10-05T17:00:00+00:00'))->getTimestamp()],
        ]);
    });

    it('returns a list, not a keyed array', function () {
        $intervals = $this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to);

        expect(array_is_list($intervals))->toBeTrue();
    });
});

describe('what the gateway hands to the integrations domain', function () {
    it('looks the connection up for the business and staff member it was given', function () {
        $this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to);

        expect($this->connectionLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffMemberId' => $this->staffMemberId,
        ]]);
    });

    it('asks the feed for exactly the window it was given', function () {
        $this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to);

        expect($this->feedWindows)->toHaveCount(1)
            ->and($this->feedWindows[0]['from'])->toBe($this->from)
            ->and($this->feedWindows[0]['to'])->toBe($this->to);
    });
});

describe('a calendar with nothing to report', function () {
    it('returns nothing when the calendar has no busy time', function () {
        expect($this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to))
            ->toBe([]);
    });

    it('returns nothing when the staff member has connected no calendar', function () {
        $this->storedConnection = null;

        expect($this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to))
            ->toBe([])
            ->and($this->feedWindows)->toBe([]);
    });
});

describe('a refusal from the integrations domain', function () {
    it('fails open to no busy time when the window ends before it starts', function () {
        $this->events = [($this->busyEvent)('2026-10-05T09:00:00+00:00', '2026-10-05T10:00:00+00:00')];

        expect($this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->to, $this->from))
            ->toBe([])
            ->and($this->connectionLookups)->toBe([]);
    });

    it('fails open to no busy time when the business cannot be found', function () {
        $this->events = [($this->busyEvent)('2026-10-05T09:00:00+00:00', '2026-10-05T10:00:00+00:00')];
        $this->businesses->shouldReceive('profileOf')->andThrow(CalendarBusinessNotFound::withId(FakeBusinessContext::BUSINESS_ID));

        expect($this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, $this->staffMemberId, $this->from, $this->to))
            ->toBe([]);
    });

    it('fails open to no busy time when the staff member id is not a uuid', function () {
        expect($this->gateway->forStaffBetween(FakeBusinessContext::BUSINESS_ID, 'not-a-uuid', $this->from, $this->to))
            ->toBe([])
            ->and($this->connectionLookups)->toBe([]);
    });
});
