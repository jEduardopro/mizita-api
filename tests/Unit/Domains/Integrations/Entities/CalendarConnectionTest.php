<?php

declare(strict_types=1);

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAlreadyConnected;
use App\Domains\Integrations\Exceptions\InvalidCalendarConnection;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->connectionId = '01930000-0000-7000-8000-0000000000c1';
    $this->staffMemberId = '01930000-0000-7000-8000-0000000000d1';
    $this->connectedAt = new DateTimeImmutable('2026-03-10T09:00:00+00:00');
    $this->reconnectedAt = new DateTimeImmutable('2026-04-02T16:30:00+00:00');

    $this->connect = fn (string $accountEmail = 'ana@example.com', string $calendarId = 'mizita-calendar@group.calendar.google.com') => CalendarConnection::connect(
        id: $this->connectionId,
        businessId: FakeBusinessContext::BUSINESS_ID,
        staffMemberId: $this->staffMemberId,
        provider: CalendarProvider::Google,
        accountEmail: $accountEmail,
        externalCalendarId: $calendarId,
        now: $this->connectedAt,
    );

    $this->awaitingReconnect = fn () => CalendarConnection::restore(
        id: $this->connectionId,
        businessId: FakeBusinessContext::BUSINESS_ID,
        staffMemberId: $this->staffMemberId,
        provider: CalendarProvider::Google,
        accountEmail: 'ana@example.com',
        externalCalendarId: 'old-calendar@group.calendar.google.com',
        status: ConnectionStatus::NeedsReconnect,
        connectedAt: $this->connectedAt,
    );
});

describe('connecting a calendar', function () {
    it('starts connected, as of the moment it was connected', function () {
        $connection = ($this->connect)();

        expect($connection->status())->toBe(ConnectionStatus::Connected)
            ->and($connection->connectedAt())->toEqual($this->connectedAt)
            ->and($connection->acceptsSync())->toBeTrue();
    });

    it('keeps the account and the calendar it publishes to', function () {
        $connection = ($this->connect)();

        expect($connection->accountEmail())->toBe('ana@example.com')
            ->and($connection->externalCalendarId())->toBe('mizita-calendar@group.calendar.google.com')
            ->and($connection->provider)->toBe(CalendarProvider::Google);
    });

    it('carries its own identity and every neighbour as the uuid', function () {
        $connection = ($this->connect)();

        expect($connection->id)->toBe($this->connectionId)
            ->and($connection->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($connection->staffMemberId)->toBe($this->staffMemberId);
    });

    it('trims the surrounding whitespace off the account email', function () {
        expect(($this->connect)("  ana@example.com\t")->accountEmail())->toBe('ana@example.com');
    });

    it('keeps an account email with accents intact', function () {
        expect(($this->connect)('josé.núñez@ejemplo.es')->accountEmail())->toBe('josé.núñez@ejemplo.es');
    });

    it('refuses a connection without the account email', function (string $accountEmail) {
        expect(fn () => ($this->connect)($accountEmail))
            ->toThrow(InvalidCalendarConnection::class, 'A calendar connection needs the email of the connected account.');
    })->with(['empty' => '', 'spaces' => '   ', 'tab and newline' => "\t\n"]);

    it('refuses a connection without the calendar it publishes to', function (string $calendarId) {
        expect(fn () => ($this->connect)('ana@example.com', $calendarId))
            ->toThrow(InvalidCalendarConnection::class, 'A calendar connection needs the calendar it publishes to.');
    })->with(['empty' => '', 'spaces' => '   ', 'tab and newline' => "\t\n"]);

    it('reports the missing account email first when both are missing', function () {
        expect(fn () => ($this->connect)('', ''))
            ->toThrow(InvalidCalendarConnection::class, 'A calendar connection needs the email of the connected account.');
    });

    it('refuses with an invalid failure the transport can classify', function () {
        $failure = null;

        try {
            ($this->connect)('');
        } catch (InvalidCalendarConnection $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('invalid_calendar_connection')
            ->and($failure?->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('restoring a calendar connection', function () {
    it('keeps the status it was stored with', function () {
        $connection = ($this->awaitingReconnect)();

        expect($connection->status())->toBe(ConnectionStatus::NeedsReconnect)
            ->and($connection->acceptsSync())->toBeFalse()
            ->and($connection->connectedAt())->toEqual($this->connectedAt);
    });

    it('skips the invariants a connection is held to when it is created', function () {
        $connection = CalendarConnection::restore(
            id: $this->connectionId,
            businessId: FakeBusinessContext::BUSINESS_ID,
            staffMemberId: $this->staffMemberId,
            provider: CalendarProvider::Google,
            accountEmail: '',
            externalCalendarId: '',
            status: ConnectionStatus::Connected,
            connectedAt: $this->connectedAt,
        );

        expect($connection->accountEmail())->toBe('')
            ->and($connection->externalCalendarId())->toBe('');
    });
});

describe('requiring a reconnect', function () {
    it('stops a connected calendar from accepting sync', function () {
        $connection = ($this->connect)();

        $connection->requireReconnect();

        expect($connection->status())->toBe(ConnectionStatus::NeedsReconnect)
            ->and($connection->acceptsSync())->toBeFalse();
    });

    it('keeps the account, the calendar and the connection time', function () {
        $connection = ($this->connect)();

        $connection->requireReconnect();

        expect($connection->accountEmail())->toBe('ana@example.com')
            ->and($connection->externalCalendarId())->toBe('mizita-calendar@group.calendar.google.com')
            ->and($connection->connectedAt())->toEqual($this->connectedAt);
    });

    it('leaves a connection already awaiting a reconnect awaiting it', function () {
        $connection = ($this->awaitingReconnect)();

        $connection->requireReconnect();

        expect($connection->status())->toBe(ConnectionStatus::NeedsReconnect);
    });
});

describe('asserting a connection is awaiting a reconnect', function () {
    it('lets a connection that needs a reconnect through', function () {
        expect(fn () => ($this->awaitingReconnect)()->assertAwaitingReconnect())->not->toThrow(Throwable::class);
    });

    it('refuses a connection that is still connected', function () {
        expect(fn () => ($this->connect)()->assertAwaitingReconnect())
            ->toThrow(CalendarAlreadyConnected::class, "Staff member [{$this->staffMemberId}] already has a connected calendar.");
    });

    it('refuses with a conflict the transport can classify', function () {
        $failure = null;

        try {
            ($this->connect)()->assertAwaitingReconnect();
        } catch (CalendarAlreadyConnected $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('calendar_already_connected')
            ->and($failure?->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('reconnecting', function () {
    it('connects a calendar that needed a reconnect again', function () {
        $connection = ($this->awaitingReconnect)();

        $connection->reconnect('ana.nueva@example.com', 'new-calendar@group.calendar.google.com', $this->reconnectedAt);

        expect($connection->status())->toBe(ConnectionStatus::Connected)
            ->and($connection->acceptsSync())->toBeTrue()
            ->and($connection->connectedAt())->toEqual($this->reconnectedAt);
    });

    it('takes the account and the calendar of the new grant', function () {
        $connection = ($this->awaitingReconnect)();

        $connection->reconnect('  ana.nueva@example.com  ', 'new-calendar@group.calendar.google.com', $this->reconnectedAt);

        expect($connection->accountEmail())->toBe('ana.nueva@example.com')
            ->and($connection->externalCalendarId())->toBe('new-calendar@group.calendar.google.com');
    });

    it('keeps its identity, its business, its staff member and its provider', function () {
        $connection = ($this->awaitingReconnect)();

        $connection->reconnect('ana.nueva@example.com', 'new-calendar@group.calendar.google.com', $this->reconnectedAt);

        expect($connection->id)->toBe($this->connectionId)
            ->and($connection->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($connection->staffMemberId)->toBe($this->staffMemberId)
            ->and($connection->provider)->toBe(CalendarProvider::Google);
    });

    it('reconnects a connection that lost its authorization while connected', function () {
        $connection = ($this->connect)();
        $connection->requireReconnect();

        $connection->reconnect('ana@example.com', 'mizita-calendar@group.calendar.google.com', $this->reconnectedAt);

        expect($connection->acceptsSync())->toBeTrue()
            ->and($connection->connectedAt())->toEqual($this->reconnectedAt);
    });

    it('refuses to reconnect a calendar that is still connected', function () {
        $connection = ($this->connect)();

        expect(fn () => $connection->reconnect('other@example.com', 'other-calendar', $this->reconnectedAt))
            ->toThrow(CalendarAlreadyConnected::class);
    });

    it('leaves a still connected calendar untouched when the reconnect is refused', function () {
        $connection = ($this->connect)();

        try {
            $connection->reconnect('other@example.com', 'other-calendar', $this->reconnectedAt);
        } catch (CalendarAlreadyConnected) {
        }

        expect($connection->accountEmail())->toBe('ana@example.com')
            ->and($connection->externalCalendarId())->toBe('mizita-calendar@group.calendar.google.com')
            ->and($connection->connectedAt())->toEqual($this->connectedAt);
    });

    it('reports the calendar is already connected before judging the new grant', function () {
        expect(fn () => ($this->connect)()->reconnect('', '', $this->reconnectedAt))
            ->toThrow(CalendarAlreadyConnected::class);
    });

    it('refuses a reconnect without the account email', function (string $accountEmail) {
        expect(fn () => ($this->awaitingReconnect)()->reconnect($accountEmail, 'new-calendar', $this->reconnectedAt))
            ->toThrow(InvalidCalendarConnection::class, 'A calendar connection needs the email of the connected account.');
    })->with(['empty' => '', 'spaces' => '   ']);

    it('refuses a reconnect without the calendar it publishes to', function (string $calendarId) {
        expect(fn () => ($this->awaitingReconnect)()->reconnect('ana@example.com', $calendarId, $this->reconnectedAt))
            ->toThrow(InvalidCalendarConnection::class, 'A calendar connection needs the calendar it publishes to.');
    })->with(['empty' => '', 'spaces' => '   ']);

    it('leaves the connection awaiting a reconnect when the new grant is incomplete', function () {
        $connection = ($this->awaitingReconnect)();

        try {
            $connection->reconnect('ana.nueva@example.com', '   ', $this->reconnectedAt);
        } catch (InvalidCalendarConnection) {
        }

        expect($connection->status())->toBe(ConnectionStatus::NeedsReconnect)
            ->and($connection->accountEmail())->toBe('ana@example.com')
            ->and($connection->externalCalendarId())->toBe('old-calendar@group.calendar.google.com')
            ->and($connection->connectedAt())->toEqual($this->connectedAt);
    });
});
