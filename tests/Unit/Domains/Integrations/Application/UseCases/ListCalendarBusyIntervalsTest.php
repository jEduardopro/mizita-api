<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\BusyIntervalData;
use App\Domains\Integrations\Application\UseCases\ListCalendarBusyIntervals;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Exceptions\ExternalCalendarUnavailable;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\ExternalEventAvailability;
use App\Domains\Integrations\ValueObjects\ExternalEventOrigin;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeBusinessProfiles;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarEventFeed;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;

beforeEach(function () {
    $this->connections = (new FakeCalendarConnectionRepository)->store(IntegrationsFixtures::connection());
    $this->businesses = (new FakeBusinessProfiles)->add(IntegrationsFixtures::BUSINESS_ID, IntegrationsFixtures::profile());
    $this->feed = new FakeCalendarEventFeed(IntegrationsFixtures::externalEvent('2026-10-02T10:00:00+00:00', '2026-10-02T11:00:00+00:00'));
    $this->input = IntegrationsFixtures::busyInput();

    $this->busy = fn () => (new ListCalendarBusyIntervals(
        $this->connections,
        $this->businesses,
        $this->feed,
    ))->handle($this->input);
});

describe('a connected calendar with hand-added events', function () {
    it('answers with the busy interval of a hand-added busy event, field by field', function () {
        $intervals = ($this->busy)()->value();

        expect($intervals)->toHaveCount(1)
            ->and($intervals[0])->toBeInstanceOf(BusyIntervalData::class)
            ->and($intervals[0]->startsAt)->toEqual(new DateTimeImmutable('2026-10-02T10:00:00+00:00'))
            ->and($intervals[0]->endsAt)->toEqual(new DateTimeImmutable('2026-10-02T11:00:00+00:00'));
    });

    it('asks the feed for the window of the input, on the staff member calendar', function () {
        ($this->busy)();

        expect($this->feed->requests)->toHaveCount(1)
            ->and($this->feed->requests[0]['connectionId'])->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($this->feed->requests[0]['from'])->toEqual(new DateTimeImmutable('2026-10-01T00:00:00+00:00'))
            ->and($this->feed->requests[0]['to'])->toEqual(new DateTimeImmutable('2026-10-08T00:00:00+00:00'));
    });

    it('looks the connection up for the staff member of the input, in the business of the input, with google', function () {
        ($this->busy)();

        expect($this->connections->staffMemberLookups)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'staffMemberId' => IntegrationsFixtures::STAFF_MEMBER_ID,
            'provider' => CalendarProvider::Google,
        ]])->and($this->businesses->lookups)->toBe([IntegrationsFixtures::BUSINESS_ID]);
    });

    it('hands the interval back in utc whatever offset the provider used', function () {
        $this->feed = new FakeCalendarEventFeed(IntegrationsFixtures::externalEvent('2026-10-02T12:00:00+02:00', '2026-10-02T13:30:00+02:00'));

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([['2026-10-02T10:00:00+00:00', '2026-10-02T11:30:00+00:00']]);
    });

    it('keeps every blocking event, in the order the feed returned them', function () {
        $this->feed = new FakeCalendarEventFeed(
            IntegrationsFixtures::externalEvent('2026-10-03T08:00:00+00:00', '2026-10-03T09:00:00+00:00'),
            IntegrationsFixtures::externalEvent('2026-10-02T08:00:00+00:00', '2026-10-02T09:00:00+00:00'),
        );

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([
            ['2026-10-03T08:00:00+00:00', '2026-10-03T09:00:00+00:00'],
            ['2026-10-02T08:00:00+00:00', '2026-10-02T09:00:00+00:00'],
        ]);
    });

    it('answers an empty list when the calendar holds no event', function () {
        $this->feed = new FakeCalendarEventFeed;

        expect(($this->busy)()->value())->toBe([]);
    });
});

describe('the events that do not block time', function () {
    it('drops an event that does not block time', function (ExternalCalendarEvent $event) {
        $this->feed = new FakeCalendarEventFeed(
            $event,
            IntegrationsFixtures::externalEvent('2026-10-02T10:00:00+00:00', '2026-10-02T11:00:00+00:00'),
        );

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([['2026-10-02T10:00:00+00:00', '2026-10-02T11:00:00+00:00']]);
    })->with([
        'hand-added but marked free' => fn () => IntegrationsFixtures::externalEvent(
            '2026-10-02T14:00:00+00:00',
            '2026-10-02T15:00:00+00:00',
            ExternalEventAvailability::Free,
        ),
        'hand-added but cancelled' => fn () => IntegrationsFixtures::externalEvent(
            '2026-10-02T14:00:00+00:00',
            '2026-10-02T15:00:00+00:00',
            ExternalEventAvailability::Cancelled,
        ),
        'an appointment mizita published itself' => fn () => IntegrationsFixtures::externalEvent(
            '2026-10-02T14:00:00+00:00',
            '2026-10-02T15:00:00+00:00',
            ExternalEventAvailability::Busy,
            ExternalEventOrigin::PublishedByMizita,
        ),
    ]);
});

describe('all-day events, in the timezone of the business', function () {
    it('blocks from local midnight to the next local midnight', function () {
        $this->feed = new FakeCalendarEventFeed(IntegrationsFixtures::allDayEvent('2026-10-02', '2026-10-03'));

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([['2026-10-01T22:00:00+00:00', '2026-10-02T22:00:00+00:00']]);
    });

    it('blocks a 23 hour day on the spring-forward day', function () {
        $this->input = IntegrationsFixtures::busyInput('2026-03-28T00:00:00+00:00', '2026-03-31T00:00:00+00:00');
        $this->feed = new FakeCalendarEventFeed(IntegrationsFixtures::allDayEvent('2026-03-29', '2026-03-30'));

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([['2026-03-28T23:00:00+00:00', '2026-03-29T22:00:00+00:00']]);
    });

    it('blocks a 25 hour day on the fall-back day', function () {
        $this->input = IntegrationsFixtures::busyInput('2026-10-24T00:00:00+00:00', '2026-10-27T00:00:00+00:00');
        $this->feed = new FakeCalendarEventFeed(IntegrationsFixtures::allDayEvent('2026-10-25', '2026-10-26'));

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([['2026-10-24T22:00:00+00:00', '2026-10-25T23:00:00+00:00']]);
    });

    it('spans several days across the fall-back boundary', function () {
        $this->feed = new FakeCalendarEventFeed(IntegrationsFixtures::allDayEvent('2026-10-24', '2026-10-27'));

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([['2026-10-23T22:00:00+00:00', '2026-10-26T23:00:00+00:00']]);
    });

    it('reads the zone from the business profile, not from utc', function () {
        $this->businesses = (new FakeBusinessProfiles)->add(IntegrationsFixtures::BUSINESS_ID, IntegrationsFixtures::profile('America/Mexico_City'));
        $this->feed = new FakeCalendarEventFeed(IntegrationsFixtures::allDayEvent('2026-10-02', '2026-10-03'));

        expect(IntegrationsFixtures::atomsOf(($this->busy)()->value()))->toBe([['2026-10-02T06:00:00+00:00', '2026-10-03T06:00:00+00:00']]);
    });
});

describe('a staff member with no usable calendar', function () {
    it('answers an empty success without reaching the provider', function (Closure $arrange) {
        $arrange($this);

        $response = ($this->busy)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([])
            ->and($this->feed->requests)->toBe([])
            ->and($this->businesses->lookups)->toBe([]);
    })->with([
        'no connection' => [fn (object $test) => $test->connections = new FakeCalendarConnectionRepository],
        'needs reconnecting' => [fn (object $test) => $test->connections = (new FakeCalendarConnectionRepository)->store(IntegrationsFixtures::awaitingReconnect())],
        'only a colleague is connected' => [fn (object $test) => $test->connections = (new FakeCalendarConnectionRepository)->store(
            IntegrationsFixtures::connection(staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID),
        )],
        'connected only in another business' => [fn (object $test) => $test->connections = (new FakeCalendarConnectionRepository)->store(
            IntegrationsFixtures::connection(businessId: IntegrationsFixtures::OTHER_BUSINESS_ID),
        )],
    ]);
});

describe('failing open when the provider fails', function () {
    it('answers an empty success rather than blocking the agenda', function (Throwable $failure) {
        $this->feed->failWith($failure);

        $response = ($this->busy)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([]);
    })->with([
        'calendar unreachable' => fn () => ExternalCalendarUnavailable::forConnection(IntegrationsFixtures::CONNECTION_ID),
        'authorization revoked' => fn () => CalendarAuthorizationRevoked::forConnection(IntegrationsFixtures::CONNECTION_ID),
    ]);
});

describe('refusals', function () {
    it('refuses a window that does not end after it starts', function (string $from, string $to) {
        $this->input = IntegrationsFixtures::busyInput($from, $to);

        $response = ($this->busy)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_busy_window')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->connections->staffMemberLookups)->toBe([])
            ->and($this->feed->requests)->toBe([]);
    })->with([
        'empty window' => ['2026-10-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00'],
        'reversed window' => ['2026-10-08T00:00:00+00:00', '2026-10-01T00:00:00+00:00'],
    ]);

    it('refuses a malformed business uuid before it reads anything', function () {
        $this->input = IntegrationsFixtures::busyInput(businessId: '42');

        $response = ($this->busy)();

        expect($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->connections->staffMemberLookups)->toBe([]);
    });

    it('refuses a malformed staff member uuid before it reads anything', function () {
        $this->input = IntegrationsFixtures::busyInput(staffMemberId: '7');

        $response = ($this->busy)();

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->connections->staffMemberLookups)->toBe([]);
    });

    it('refuses when the business of a connected calendar no longer exists', function () {
        $this->businesses = new FakeBusinessProfiles;

        $response = ($this->busy)();

        expect($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->feed->requests)->toBe([]);
    });
});
