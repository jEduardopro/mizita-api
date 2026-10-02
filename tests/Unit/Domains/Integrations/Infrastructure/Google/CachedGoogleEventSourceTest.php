<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Google\CachedGoogleEventSource;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleEventSource;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

function cachedSourceTimedEvent(string $id, string $startsAt, string $endsAt): array
{
    return ['id' => $id, 'start' => ['dateTime' => $startsAt], 'end' => ['dateTime' => $endsAt]];
}

function cachedSourceAllDayEvent(string $id, string $startDate, string $endDate): array
{
    return ['id' => $id, 'start' => ['date' => $startDate], 'end' => ['date' => $endDate]];
}

beforeEach(function () {
    Carbon::setTestNow(IntegrationsFixtures::NOW);

    $this->reads = [];
    $this->answers = [[['id' => 'evt-1']]];
    $this->inner = Mockery::mock(GoogleEventSource::class);
    $this->inner->shouldReceive('itemsBetween')->andReturnUsing(function ($connection, DateTimeImmutable $from, DateTimeImmutable $to) {
        $this->reads[] = [$connection->id, $connection->externalCalendarId(), $from->format(DATE_ATOM), $to->format(DATE_ATOM)];
        $answer = array_shift($this->answers) ?? [];

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        return $answer;
    });

    $this->source = new CachedGoogleEventSource($this->inner, new Repository(new ArrayStore), 120);

    $this->from = new DateTimeImmutable('2026-10-25T00:00:00+02:00');
    $this->to = new DateTimeImmutable('2026-10-26T00:00:00+01:00');

    $this->read = fn (
        ?DateTimeImmutable $to = null,
        string $connectionId = IntegrationsFixtures::CONNECTION_ID,
        string $calendarId = IntegrationsFixtures::CALENDAR_ID,
        ?DateTimeImmutable $from = null,
    ) => $this->source->itemsBetween(
        IntegrationsFixtures::connection(id: $connectionId, externalCalendarId: $calendarId),
        $from ?? $this->from,
        $to ?? $this->to,
    );

    $this->windows = fn (): array => array_map(
        static fn (array $read): array => [$read[2], $read[3]],
        $this->reads,
    );

    $this->identities = static fn (array $items): array => array_map(
        static fn (array $item): mixed => $item['id'] ?? null,
        $items,
    );

    $this->advance = fn (int $seconds) => Carbon::setTestNow(Carbon::parse(IntegrationsFixtures::NOW)->addSeconds($seconds));
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('within the cache lifetime', function () {
    it('hands back what the source answered', function () {
        expect(($this->read)())->toBe([['id' => 'evt-1']]);
    });

    it('answers a second read of the same window from the cache', function () {
        $this->answers = [[['id' => 'evt-1']], [['id' => 'evt-2']]];

        ($this->read)();
        ($this->advance)(119);

        expect(($this->read)())->toBe([['id' => 'evt-1']])
            ->and($this->reads)->toHaveCount(1);
    });

    it('caches a calendar with no events too', function () {
        $this->answers = [[], [['id' => 'evt-2']]];

        ($this->read)();

        expect(($this->read)())->toBe([])
            ->and($this->reads)->toHaveCount(1);
    });

    it('answers another window of the same month from the month it already holds', function () {
        ($this->read)();
        ($this->read)(new DateTimeImmutable('2026-10-27T00:00:00+01:00'));
        ($this->read)(new DateTimeImmutable('2026-10-31T00:00:00+01:00'), from: new DateTimeImmutable('2026-10-03T00:00:00+02:00'));

        expect($this->reads)->toHaveCount(1);
    });

    it('asks the source again for a window in a month it has not read yet', function () {
        ($this->read)();
        ($this->read)(
            new DateTimeImmutable('2026-11-11T00:00:00+01:00'),
            from: new DateTimeImmutable('2026-11-10T00:00:00+01:00'),
        );

        expect(($this->windows)())->toBe([
            ['2026-10-01T00:00:00+00:00', '2026-11-01T00:00:00+00:00'],
            ['2026-11-01T00:00:00+00:00', '2026-12-01T00:00:00+00:00'],
        ]);
    });

    it('never answers one connection from the cache of another', function () {
        ($this->read)();
        ($this->read)(connectionId: IntegrationsFixtures::OTHER_CONNECTION_ID);

        expect($this->reads)->toHaveCount(2);
    });

    it('asks the source again once the connection points to another calendar', function () {
        ($this->read)();
        ($this->read)(calendarId: 'mizita-new@group.calendar.google.com');

        expect($this->reads)->toHaveCount(2);
    });

    it('keys the same instant the same way whatever offset it was written in', function () {
        ($this->read)();
        ($this->read)(new DateTimeImmutable('2026-10-25T23:00:00+00:00'));

        expect($this->reads)->toHaveCount(1);
    });
});

describe('the months it asks the source for', function () {
    it('asks the source for the whole UTC calendar month around the window', function () {
        ($this->read)();

        expect(($this->windows)())->toBe([
            ['2026-10-01T00:00:00+00:00', '2026-11-01T00:00:00+00:00'],
        ]);
    });

    it('asks the source once per month the window crosses', function () {
        $this->answers = [[], [], []];

        ($this->read)(new DateTimeImmutable('2026-12-02T00:00:00+01:00'));

        expect(($this->windows)())->toBe([
            ['2026-10-01T00:00:00+00:00', '2026-11-01T00:00:00+00:00'],
            ['2026-11-01T00:00:00+00:00', '2026-12-01T00:00:00+00:00'],
            ['2026-12-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00'],
        ]);
    });

    it('asks nothing about the month a window ends exactly at the start of', function () {
        ($this->read)(new DateTimeImmutable('2026-11-01T00:00:00+00:00'));

        expect(($this->windows)())->toBe([
            ['2026-10-01T00:00:00+00:00', '2026-11-01T00:00:00+00:00'],
        ]);
    });

    it('buckets a local midnight by the UTC month it falls in, not the local one', function () {
        $this->answers = [[], []];

        ($this->read)(
            new DateTimeImmutable('2026-11-02T00:00:00+01:00'),
            from: new DateTimeImmutable('2026-11-01T00:00:00+01:00'),
        );

        expect(($this->windows)())->toBe([
            ['2026-10-01T00:00:00+00:00', '2026-11-01T00:00:00+00:00'],
            ['2026-11-01T00:00:00+00:00', '2026-12-01T00:00:00+00:00'],
        ]);
    });

    it('answers a later window spanning cached months without asking the source again', function () {
        $this->answers = [[], []];

        ($this->read)(new DateTimeImmutable('2026-11-20T00:00:00+01:00'));
        ($this->read)(
            new DateTimeImmutable('2026-11-05T00:00:00+01:00'),
            from: new DateTimeImmutable('2026-10-28T00:00:00+01:00'),
        );

        expect($this->reads)->toHaveCount(2);
    });
});

describe('the events it hands back', function () {
    it('hands back only the events of the month that overlap the window', function () {
        $this->answers = [[
            cachedSourceTimedEvent('before', '2026-10-20T10:00:00+02:00', '2026-10-20T11:00:00+02:00'),
            cachedSourceTimedEvent('inside', '2026-10-25T10:00:00+01:00', '2026-10-25T11:00:00+01:00'),
            cachedSourceTimedEvent('after', '2026-10-28T10:00:00+01:00', '2026-10-28T11:00:00+01:00'),
        ]];

        expect(($this->identities)(($this->read)()))->toBe(['inside']);
    });

    it('keeps an event that only straddles one edge of the window', function () {
        $this->answers = [[
            cachedSourceTimedEvent('straddles-start', '2026-10-24T21:00:00+00:00', '2026-10-24T22:30:00+00:00'),
            cachedSourceTimedEvent('straddles-end', '2026-10-25T22:30:00+00:00', '2026-10-25T23:30:00+00:00'),
        ]];

        expect(($this->identities)(($this->read)()))->toBe(['straddles-start', 'straddles-end']);
    });

    it('drops an event that only touches the window at its edge', function () {
        $this->answers = [[
            cachedSourceTimedEvent('ends-at-from', '2026-10-24T21:00:00+00:00', '2026-10-24T22:00:00+00:00'),
            cachedSourceTimedEvent('starts-at-to', '2026-10-25T23:00:00+00:00', '2026-10-26T00:00:00+00:00'),
        ]];

        expect(($this->read)())->toBe([]);
    });

    it('filters the same cached month differently for each window', function () {
        $this->answers = [[
            cachedSourceTimedEvent('sunday', '2026-10-25T10:00:00+01:00', '2026-10-25T11:00:00+01:00'),
            cachedSourceTimedEvent('tuesday', '2026-10-27T10:00:00+01:00', '2026-10-27T11:00:00+01:00'),
        ]];

        $sunday = ($this->read)();
        $tuesday = ($this->read)(
            new DateTimeImmutable('2026-10-28T00:00:00+01:00'),
            from: new DateTimeImmutable('2026-10-27T00:00:00+01:00'),
        );

        expect(($this->identities)($sunday))->toBe(['sunday'])
            ->and(($this->identities)($tuesday))->toBe(['tuesday'])
            ->and($this->reads)->toHaveCount(1);
    });

    it('hands back an event both months returned only once', function () {
        $crossing = cachedSourceTimedEvent('crossing', '2026-10-31T23:00:00+00:00', '2026-11-01T01:00:00+00:00');
        $this->answers = [[$crossing], [$crossing]];

        $items = ($this->read)(
            new DateTimeImmutable('2026-11-02T00:00:00+00:00'),
            from: new DateTimeImmutable('2026-10-31T00:00:00+00:00'),
        );

        expect($items)->toBe([$crossing])
            ->and($this->reads)->toHaveCount(2);
    });

    it('keeps two different events that carry no id', function () {
        $first = ['start' => ['dateTime' => '2026-10-25T10:00:00+01:00'], 'end' => ['dateTime' => '2026-10-25T11:00:00+01:00']];
        $second = ['start' => ['dateTime' => '2026-10-25T12:00:00+01:00'], 'end' => ['dateTime' => '2026-10-25T13:00:00+01:00']];
        $this->answers = [[$first, $second]];

        expect(($this->read)())->toBe([$first, $second]);
    });

    it('keeps an event whose times it cannot read, rather than hide a busy period', function () {
        $unreadable = ['id' => 'unreadable', 'start' => ['dateTime' => 'not a date'], 'end' => ['dateTime' => 'nor this']];
        $this->answers = [[$unreadable]];

        expect(($this->read)())->toBe([$unreadable]);
    });

    it('keeps an all-day event that may overlap the window somewhere on earth', function () {
        $this->answers = [[
            cachedSourceAllDayEvent('the-day-before', '2026-10-24', '2026-10-25'),
            cachedSourceAllDayEvent('weeks-before', '2026-10-10', '2026-10-11'),
        ]];

        expect(($this->identities)(($this->read)(
            new DateTimeImmutable('2026-10-26T05:00:00+00:00'),
            from: new DateTimeImmutable('2026-10-25T05:00:00+00:00'),
        )))->toBe(['the-day-before']);
    });
});

describe('once the cache lifetime is over', function () {
    it('asks the source again after 120 seconds', function () {
        $this->answers = [[['id' => 'evt-1']], [['id' => 'evt-2']]];

        ($this->read)();
        ($this->advance)(120);

        expect(($this->read)())->toBe([['id' => 'evt-2']])
            ->and($this->reads)->toHaveCount(2);
    });
});

describe('a source that fails', function () {
    it('lets the failure through', function () {
        $this->answers = [GoogleApiFailure::unexpectedStatus('GET', '/calendars/x/events', 503)];

        expect(fn () => ($this->read)())->toThrow(GoogleApiFailure::class);
    });

    it('never caches the failure, so the next read tries Google again', function () {
        $this->answers = [GoogleApiFailure::unexpectedStatus('GET', '/calendars/x/events', 503), [['id' => 'evt-2']]];

        try {
            ($this->read)();
        } catch (GoogleApiFailure) {
        }

        expect(($this->read)())->toBe([['id' => 'evt-2']])
            ->and($this->reads)->toHaveCount(2);
    });
});
