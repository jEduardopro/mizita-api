<?php

declare(strict_types=1);

use App\Domains\Integrations\ValueObjects\AllDayEventSpan;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\ExternalEventAvailability;
use App\Domains\Integrations\ValueObjects\ExternalEventOrigin;
use App\Domains\Integrations\ValueObjects\TimedEventSpan;

beforeEach(function () {
    $this->timedSpan = new TimedEventSpan(
        new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
        new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
    );
});

describe('deciding whether an event blocks time', function () {
    it('blocks for an event added by hand and marked busy', function () {
        $event = new ExternalCalendarEvent($this->timedSpan, ExternalEventOrigin::AddedByHand, ExternalEventAvailability::Busy);

        expect($event->blocksTime())->toBeTrue();
    });

    it('never blocks for an event Mizita published itself', function (ExternalEventAvailability $availability) {
        $event = new ExternalCalendarEvent($this->timedSpan, ExternalEventOrigin::PublishedByMizita, $availability);

        expect($event->blocksTime())->toBeFalse();
    })->with([
        'busy' => [ExternalEventAvailability::Busy],
        'free' => [ExternalEventAvailability::Free],
        'cancelled' => [ExternalEventAvailability::Cancelled],
    ]);

    it('does not block for an event added by hand and marked free', function () {
        $event = new ExternalCalendarEvent($this->timedSpan, ExternalEventOrigin::AddedByHand, ExternalEventAvailability::Free);

        expect($event->blocksTime())->toBeFalse();
    });

    it('does not block for an event added by hand and then cancelled', function () {
        $event = new ExternalCalendarEvent($this->timedSpan, ExternalEventOrigin::AddedByHand, ExternalEventAvailability::Cancelled);

        expect($event->blocksTime())->toBeFalse();
    });

    it('blocks for a busy all-day event added by hand', function () {
        $event = new ExternalCalendarEvent(
            new AllDayEventSpan('2026-03-10', '2026-03-11'),
            ExternalEventOrigin::AddedByHand,
            ExternalEventAvailability::Busy,
        );

        expect($event->blocksTime())->toBeTrue();
    });
});

describe('reading the busy time of an event', function () {
    it('reads a timed event as the instants it runs between', function () {
        $event = new ExternalCalendarEvent($this->timedSpan, ExternalEventOrigin::AddedByHand, ExternalEventAvailability::Busy);

        $interval = $event->intervalIn(new DateTimeZone('Europe/Madrid'));

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-10T09:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-10T10:00:00+00:00');
    });

    it('reads an all-day event in the zone of the business', function () {
        $event = new ExternalCalendarEvent(
            new AllDayEventSpan('2026-03-29', '2026-03-30'),
            ExternalEventOrigin::AddedByHand,
            ExternalEventAvailability::Busy,
        );

        $interval = $event->intervalIn(new DateTimeZone('Europe/Madrid'));

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-28T23:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-29T22:00:00+00:00');
    });
});
