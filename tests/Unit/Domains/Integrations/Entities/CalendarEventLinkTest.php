<?php

declare(strict_types=1);

use App\Domains\Integrations\Entities\CalendarEventLink;
use App\Domains\Integrations\Exceptions\InvalidCalendarEventLink;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->linkId = '01930000-0000-7000-8000-0000000000e1';
    $this->connectionId = '01930000-0000-7000-8000-0000000000c1';
    $this->appointmentId = '01930000-0000-7000-8000-0000000000f1';

    $this->create = fn (string $externalEventId = 'google-event-1') => CalendarEventLink::create(
        id: $this->linkId,
        businessId: FakeBusinessContext::BUSINESS_ID,
        connectionId: $this->connectionId,
        appointmentId: $this->appointmentId,
        externalEventId: $externalEventId,
    );
});

describe('linking an appointment to an external event', function () {
    it('points at the external event it was created for', function () {
        expect(($this->create)()->externalEventId())->toBe('google-event-1');
    });

    it('carries its own identity and every neighbour as the uuid', function () {
        $link = ($this->create)();

        expect($link->id)->toBe($this->linkId)
            ->and($link->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($link->connectionId)->toBe($this->connectionId)
            ->and($link->appointmentId)->toBe($this->appointmentId);
    });

    it('refuses a link that points at no event', function (string $externalEventId) {
        expect(fn () => ($this->create)($externalEventId))
            ->toThrow(InvalidCalendarEventLink::class, 'A calendar event link needs the external event it points to.');
    })->with(['empty' => '', 'spaces' => '   ', 'tab and newline' => "\t\n"]);

    it('refuses with an invalid failure the transport can classify', function () {
        $failure = null;

        try {
            ($this->create)('');
        } catch (InvalidCalendarEventLink $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('invalid_calendar_event_link')
            ->and($failure?->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('restoring a link', function () {
    it('skips the invariant a link is held to when it is created', function () {
        $link = CalendarEventLink::restore(
            $this->linkId,
            FakeBusinessContext::BUSINESS_ID,
            $this->connectionId,
            $this->appointmentId,
            '',
        );

        expect($link->externalEventId())->toBe('');
    });
});

describe('telling which connection a link belongs to', function () {
    it('belongs to the connection it was created under', function () {
        expect(($this->create)()->belongsTo($this->connectionId))->toBeTrue();
    });

    it('belongs to no other connection', function (string $connectionId) {
        expect(($this->create)()->belongsTo($connectionId))->toBeFalse();
    })->with([
        'another connection' => '01930000-0000-7000-8000-0000000000c2',
        'an empty id' => '',
        'the uppercase spelling of its own id' => '01930000-0000-7000-8000-0000000000C1',
    ]);
});

describe('repointing a link', function () {
    it('points at the external event it was repointed to', function () {
        $link = ($this->create)();

        $link->repointTo('google-event-2');

        expect($link->externalEventId())->toBe('google-event-2');
    });

    it('refuses to point at no event', function (string $externalEventId) {
        expect(fn () => ($this->create)()->repointTo($externalEventId))
            ->toThrow(InvalidCalendarEventLink::class);
    })->with(['empty' => '', 'spaces' => '   ']);

    it('keeps pointing at the previous event when the repoint is refused', function () {
        $link = ($this->create)();

        try {
            $link->repointTo('   ');
        } catch (InvalidCalendarEventLink) {
        }

        expect($link->externalEventId())->toBe('google-event-1');
    });
});
