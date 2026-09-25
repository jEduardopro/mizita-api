<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Google\CachedGoogleEventSource;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleEventSource;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

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
    ) => $this->source->itemsBetween(
        IntegrationsFixtures::connection(id: $connectionId, externalCalendarId: $calendarId),
        $this->from,
        $to ?? $this->to,
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

    it('asks the source again for another window', function () {
        ($this->read)();
        ($this->read)(new DateTimeImmutable('2026-10-27T00:00:00+01:00'));

        expect($this->reads)->toHaveCount(2);
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
