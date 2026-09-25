<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Exceptions\ExternalCalendarUnavailable;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarEventFeed;
use App\Domains\Integrations\Infrastructure\Google\GoogleEventSource;
use App\Domains\Integrations\ValueObjects\AllDayEventSpan;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\ExternalEventAvailability;
use App\Domains\Integrations\ValueObjects\ExternalEventOrigin;
use App\Domains\Integrations\ValueObjects\TimedEventSpan;
use App\Shared\Contracts\Clock;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Support\FakeClock;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\StoredCalendarCredentials;

uses(TestCase::class);

beforeEach(function () {
    $this->source = Mockery::mock(GoogleEventSource::class);
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->feed = new GoogleCalendarEventFeed($this->source, $this->logger);

    $this->from = new DateTimeImmutable('2026-03-29T00:00:00+01:00');
    $this->to = new DateTimeImmutable('2026-03-30T00:00:00+02:00');

    $this->sourceAnswers = fn (array $items) => $this->source->shouldReceive('itemsBetween')->once()->andReturn($items);
    $this->sourceFails = fn (Throwable $failure) => $this->source->shouldReceive('itemsBetween')->once()->andThrow($failure);

    $this->events = fn (): array => $this->feed->eventsBetween(IntegrationsFixtures::connection(), $this->from, $this->to);

    $this->timed = fn (array $overrides = []): array => [
        'id' => 'evt-1',
        'start' => ['dateTime' => '2026-03-29T10:00:00+02:00'],
        'end' => ['dateTime' => '2026-03-29T11:00:00+02:00'],
        ...$overrides,
    ];
});

describe('reading the window', function () {
    it('asks the source for exactly the connection and the window it was handed', function () {
        $this->source->shouldReceive('itemsBetween')->once()
            ->with(
                Mockery::on(fn ($connection): bool => $connection->id === IntegrationsFixtures::CONNECTION_ID),
                $this->from,
                $this->to,
            )
            ->andReturn([]);

        expect(($this->events)())->toBe([]);
    });
});

describe('turning Google items into events', function () {
    it('reads a timed event as a busy event somebody added by hand', function () {
        ($this->sourceAnswers)([($this->timed)()]);

        $event = ($this->events)()[0];

        expect($event)->toBeInstanceOf(ExternalCalendarEvent::class)
            ->and($event->span)->toBeInstanceOf(TimedEventSpan::class)
            ->and($event->span->startsAt)->toEqual(new DateTimeImmutable('2026-03-29T08:00:00+00:00'))
            ->and($event->span->endsAt)->toEqual(new DateTimeImmutable('2026-03-29T09:00:00+00:00'))
            ->and($event->origin)->toBe(ExternalEventOrigin::AddedByHand)
            ->and($event->availability)->toBe(ExternalEventAvailability::Busy)
            ->and($event->blocksTime())->toBeTrue();
    });

    it('recognises an event Mizita published by its appointment tag', function () {
        ($this->sourceAnswers)([($this->timed)([
            'extendedProperties' => ['private' => ['mizita_appointment_id' => IntegrationsFixtures::APPOINTMENT_ID]],
        ])]);

        expect(($this->events)()[0]->origin)->toBe(ExternalEventOrigin::PublishedByMizita);
    });

    it('reads an event with private properties but no appointment tag as added by hand', function () {
        ($this->sourceAnswers)([($this->timed)(['extendedProperties' => ['private' => ['other_app' => 'x']]])]);

        expect(($this->events)()[0]->origin)->toBe(ExternalEventOrigin::AddedByHand);
    });

    it('reads a cancelled event as cancelled', function () {
        ($this->sourceAnswers)([($this->timed)(['status' => 'cancelled', 'transparency' => 'transparent'])]);

        expect(($this->events)()[0]->availability)->toBe(ExternalEventAvailability::Cancelled);
    });

    it('reads a transparent event as free', function () {
        ($this->sourceAnswers)([($this->timed)(['transparency' => 'transparent'])]);

        expect(($this->events)()[0]->availability)->toBe(ExternalEventAvailability::Free);
    });

    it('reads an all day event as the local dates it covers', function () {
        ($this->sourceAnswers)([[
            'id' => 'evt-holiday',
            'start' => ['date' => '2026-03-29'],
            'end' => ['date' => '2026-03-30'],
        ]]);

        $span = ($this->events)()[0]->span;

        expect($span)->toBeInstanceOf(AllDayEventSpan::class)
            ->and($span->firstDate)->toBe('2026-03-29')
            ->and($span->dayAfterLastDate)->toBe('2026-03-30');
    });

    it('drops an item it cannot read and keeps the rest', function (array $unreadable) {
        ($this->sourceAnswers)([$unreadable, ($this->timed)(['id' => 'evt-kept'])]);

        $events = ($this->events)();

        expect($events)->toHaveCount(1)
            ->and($events[0]->span->startsAt)->toEqual(new DateTimeImmutable('2026-03-29T08:00:00+00:00'));
    })->with([
        'no start nor end' => [['id' => 'evt-bare']],
        'a timed event ending before it starts' => [[
            'start' => ['dateTime' => '2026-03-29T11:00:00+02:00'],
            'end' => ['dateTime' => '2026-03-29T10:00:00+02:00'],
        ]],
        'a timed event of no length' => [[
            'start' => ['dateTime' => '2026-03-29T10:00:00+02:00'],
            'end' => ['dateTime' => '2026-03-29T10:00:00+02:00'],
        ]],
        'a malformed instant' => [[
            'start' => ['dateTime' => 'not-a-date'],
            'end' => ['dateTime' => '2026-03-29T10:00:00+02:00'],
        ]],
        'a malformed all day date' => [[
            'start' => ['date' => '2026-02-30'],
            'end' => ['date' => '2026-03-01'],
        ]],
        'an all day event ending before it starts' => [[
            'start' => ['date' => '2026-03-30'],
            'end' => ['date' => '2026-03-29'],
        ]],
        'a start that is not an object' => [['start' => 'soon', 'end' => 'later']],
    ]);

    it('hands back nothing for a calendar with no events', function () {
        ($this->sourceAnswers)([]);

        expect(($this->events)())->toBe([]);
    });
});

describe('a revoked authorization', function () {
    it('lets the revocation through as the domain failure it is', function () {
        $revoked = CalendarAuthorizationRevoked::forConnection(IntegrationsFixtures::CONNECTION_ID);
        ($this->sourceFails)($revoked);
        $this->logger->shouldNotReceive('warning');

        $caught = null;

        try {
            ($this->events)();
        } catch (CalendarAuthorizationRevoked $failure) {
            $caught = $failure;
        }

        expect($caught)->toBe($revoked);
    });
});

describe('Google out of reach', function () {
    it('surfaces a Google failure as the calendar being unavailable', function () {
        ($this->sourceFails)(GoogleApiFailure::unreachable('GET', '/calendars/x/events', 'ConnectException'));
        $this->logger->shouldReceive('warning')->once();

        $caught = null;

        try {
            ($this->events)();
        } catch (ExternalCalendarUnavailable $failure) {
            $caught = $failure;
        }

        expect($caught?->errorCode())->toBe('external_calendar_unavailable')
            ->and($caught?->kind())->toBe(DomainFailureKind::Conflict);
    });

    it('surfaces any other failure as the calendar being unavailable', function () {
        ($this->sourceFails)(new RuntimeException('Unexpected'));
        $this->logger->shouldReceive('warning')->once();

        expect(fn () => ($this->events)())->toThrow(ExternalCalendarUnavailable::class);
    });

    it('logs one warning naming the connection and the sanitised Google failure', function () {
        ($this->sourceFails)(GoogleApiFailure::unexpectedStatus('GET', '/calendars/x/events', 503));
        $this->logger->shouldReceive('warning')->once()->with(
            Mockery::type('string'),
            [
                'calendar_connection_id' => IntegrationsFixtures::CONNECTION_ID,
                'failure' => 'Google answered GET /calendars/x/events with HTTP 503.',
            ],
        );

        expect(fn () => ($this->events)())->toThrow(ExternalCalendarUnavailable::class);
    });

    it('logs only the class of a failure it does not know, never its message', function () {
        ($this->sourceFails)(new RuntimeException('Bearer '.IntegrationsFixtures::ACCESS_TOKEN));
        $this->logger->shouldReceive('warning')->once()->with(
            Mockery::type('string'),
            ['calendar_connection_id' => IntegrationsFixtures::CONNECTION_ID, 'failure' => RuntimeException::class],
        );

        expect(fn () => ($this->events)())->toThrow(ExternalCalendarUnavailable::class);
    });

    it('never carries the underlying failure in the chain', function () {
        ($this->sourceFails)(new RuntimeException('Bearer '.IntegrationsFixtures::ACCESS_TOKEN));
        $this->logger->shouldReceive('warning')->once();

        $caught = null;

        try {
            ($this->events)();
        } catch (ExternalCalendarUnavailable $failure) {
            $caught = $failure;
        }

        expect($caught?->getPrevious())->toBeNull()
            ->and($caught?->getMessage())->not->toContain(IntegrationsFixtures::ACCESS_TOKEN);
    });
});

describe('as the container wires it for busy times', function () {
    beforeEach(function () {
        Http::preventStrayRequests();

        $this->credentials = StoredCalendarCredentials::install()->hold();
        $this->app->instance(Clock::class, new FakeClock(IntegrationsFixtures::now()));
        $this->app->instance(LoggerInterface::class, new NullLogger);
        $this->travelTo(new DateTimeImmutable(IntegrationsFixtures::NOW));

        $this->wiredEvents = fn (): array => $this->app->make(GoogleCalendarEventFeed::class)
            ->eventsBetween(IntegrationsFixtures::connection(), $this->from, $this->to);
    });

    afterEach(function () {
        $this->credentials->uninstall();
    });

    it('answers a repeated read within 120 seconds from the cache', function () {
        Http::fakeSequence()->push(['items' => [($this->timed)()]])->push(['items' => []]);

        ($this->wiredEvents)();
        $this->travel(119)->seconds();

        expect(($this->wiredEvents)())->toHaveCount(1);

        Http::assertSentCount(1);
    });

    it('reads Google again once 120 seconds have passed', function () {
        Http::fakeSequence()->push(['items' => [($this->timed)()]])->push(['items' => []]);

        ($this->wiredEvents)();
        $this->travel(120)->seconds();

        expect(($this->wiredEvents)())->toBe([]);

        Http::assertSentCount(2);
    });

    it('surfaces a timed out read as the calendar being unavailable', function () {
        Http::fakeSequence()->pushFailedConnection('cURL error 28: Operation timed out after 3001 milliseconds');

        expect(fn () => ($this->wiredEvents)())->toThrow(ExternalCalendarUnavailable::class);
    });
});
