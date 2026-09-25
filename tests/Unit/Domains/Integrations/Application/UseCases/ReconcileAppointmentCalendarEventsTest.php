<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\ReconcileAppointmentCalendarEventsInput;
use App\Domains\Integrations\Application\UseCases\ReconcileAppointmentCalendarEvents;
use App\Domains\Integrations\ValueObjects\AppointmentLifecycle;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeAppointmentFeed;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarEventLinkRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarEventPublisher;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsJournal;

beforeEach(function () {
    $this->journal = new IntegrationsJournal;
    $this->appointments = (new FakeAppointmentFeed($this->journal))->add(IntegrationsFixtures::snapshot());
    $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(IntegrationsFixtures::connection());
    $this->links = new FakeCalendarEventLinkRepository($this->journal);
    $this->publisher = new FakeCalendarEventPublisher($this->journal);
    $this->ids = new FixedIdGenerator(IntegrationsFixtures::NEW_LINK_ID);

    $this->reconcile = fn (string $appointmentId = IntegrationsFixtures::APPOINTMENT_ID) => (new ReconcileAppointmentCalendarEvents(
        $this->appointments,
        $this->connections,
        $this->links,
        $this->publisher,
        $this->ids,
        IntegrationsFixtures::MIZITA_LINK,
    ))->handle(new ReconcileAppointmentCalendarEventsInput($appointmentId));

    $this->reassignTo = function (string $staffMemberId): void {
        $this->appointments->add(IntegrationsFixtures::snapshot(staffMemberId: $staffMemberId));
    };
});

describe('an active appointment on a connected calendar, not yet published', function () {
    it('answers with an empty success', function () {
        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('publishes a new event to the calendar of the assigned staff member', function () {
        ($this->reconcile)();

        expect($this->publisher->published)->toHaveCount(1)
            ->and($this->publisher->published[0]['connectionId'])->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($this->publisher->published[0]['knownEventId'])->toBeNull();
    });

    it('publishes the draft of the appointment, carrying the mizita link', function () {
        ($this->reconcile)();

        $draft = $this->publisher->published[0]['draft'];

        expect($draft->appointmentId)->toBe(IntegrationsFixtures::APPOINTMENT_ID)
            ->and($draft->title)->toBe('Corte de pelo — Begoña Muñoz')
            ->and($draft->description)->toBe("Ref. MZ-7Q4K\n".IntegrationsFixtures::MIZITA_LINK)
            ->and($draft->startsAt)->toEqual(IntegrationsFixtures::instant(IntegrationsFixtures::STARTS_AT))
            ->and($draft->endsAt)->toEqual(IntegrationsFixtures::instant(IntegrationsFixtures::ENDS_AT))
            ->and($draft->timezone)->toBe(IntegrationsFixtures::BUSINESS_TIMEZONE);
    });

    it('remembers the published event in exactly one new link', function () {
        $this->publisher->answerWith('googleevent0042');

        ($this->reconcile)();

        expect($this->links->saved)->toBe([[
            'id' => IntegrationsFixtures::NEW_LINK_ID,
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'connectionId' => IntegrationsFixtures::CONNECTION_ID,
            'appointmentId' => IntegrationsFixtures::APPOINTMENT_ID,
            'externalEventId' => 'googleevent0042',
        ]]);
    });

    it('publishes before it remembers the link', function () {
        ($this->reconcile)();

        expect(array_search('publisher.publish', $this->journal->entries, true))
            ->toBeLessThan(array_search('links.save', $this->journal->entries, true));
    });

    it('reads the links and the connection inside the business of the appointment', function () {
        ($this->reconcile)();

        expect($this->links->lookups)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'appointmentId' => IntegrationsFixtures::APPOINTMENT_ID,
        ]])->and($this->connections->staffMemberLookups)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'staffMemberId' => IntegrationsFixtures::STAFF_MEMBER_ID,
            'provider' => CalendarProvider::Google,
        ]]);
    });

    it('withdraws nothing', function () {
        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([]);
    });
});

describe('an active appointment already on the calendar', function () {
    beforeEach(function () {
        $this->links->store(IntegrationsFixtures::link());
    });

    it('patches the known event instead of creating a second one', function () {
        ($this->reconcile)();

        expect($this->publisher->published)->toHaveCount(1)
            ->and($this->publisher->published[0]['connectionId'])->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($this->publisher->published[0]['knownEventId'])->toBe(IntegrationsFixtures::EXTERNAL_EVENT_ID);
    });

    it('keeps the one link it had, under the same uuid', function () {
        ($this->reconcile)();

        expect($this->links->all())->toHaveCount(1)
            ->and($this->links->all()[0]->id)->toBe(IntegrationsFixtures::LINK_ID)
            ->and($this->links->saved[0]['id'])->toBe(IntegrationsFixtures::LINK_ID);
    });

    it('repoints the link when the provider had to recreate the event', function () {
        $this->publisher->answerWith(IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID);

        ($this->reconcile)();

        expect($this->links->saved)->toHaveCount(1)
            ->and($this->links->saved[0]['id'])->toBe(IntegrationsFixtures::LINK_ID)
            ->and($this->links->saved[0]['externalEventId'])->toBe(IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID);
    });

    it('withdraws nothing and deletes no link', function () {
        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([])
            ->and($this->links->deleted)->toBe([]);
    });

    it('forgets the stray duplicate link once its event is withdrawn', function () {
        $this->links->store(IntegrationsFixtures::link(
            id: IntegrationsFixtures::SECOND_LINK_ID,
            externalEventId: IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID,
        ));

        ($this->reconcile)();

        expect($this->links->deleted)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'id' => IntegrationsFixtures::SECOND_LINK_ID,
        ]])->and($this->publisher->published)->toHaveCount(1)
            ->and($this->links->saved)->toHaveCount(1)
            ->and($this->links->saved[0]['id'])->toBe(IntegrationsFixtures::LINK_ID);
    });

    it('withdraws a stray duplicate link on the same calendar and keeps the first', function () {
        $this->links->store(IntegrationsFixtures::link(
            id: IntegrationsFixtures::SECOND_LINK_ID,
            externalEventId: IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID,
        ));

        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([[
            'connectionId' => IntegrationsFixtures::CONNECTION_ID,
            'externalEventId' => IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID,
        ]])->and($this->publisher->published[0]['knownEventId'])->toBe(IntegrationsFixtures::EXTERNAL_EVENT_ID)
            ->and(array_map(static fn ($link) => $link->id, $this->links->all()))->toBe([IntegrationsFixtures::LINK_ID]);
    });
});

describe('running the reconciliation more than once', function () {
    it('never adds a second link for the same appointment and calendar', function () {
        ($this->reconcile)();
        ($this->reconcile)();
        ($this->reconcile)();

        expect($this->links->all())->toHaveCount(1)
            ->and($this->links->all()[0]->id)->toBe(IntegrationsFixtures::NEW_LINK_ID);
    });

    it('patches the event it published on the first run', function () {
        $this->publisher->answerWith('googleevent0042');

        ($this->reconcile)();
        ($this->reconcile)();

        expect(array_column($this->publisher->published, 'knownEventId'))->toBe([null, 'googleevent0042']);
    });

    it('never withdraws the event it is keeping', function () {
        ($this->reconcile)();
        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([])
            ->and($this->links->deleted)->toBe([]);
    });
});

describe('an active appointment with nowhere to publish', function () {
    it('publishes nothing and remembers nothing when the staff member has no calendar', function () {
        $this->connections = new FakeCalendarConnectionRepository($this->journal);

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->publisher->published)->toBe([])
            ->and($this->links->saved)->toBe([]);
    });

    it('publishes nothing and remembers nothing while the calendar needs reconnecting', function () {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(IntegrationsFixtures::awaitingReconnect());

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->publisher->published)->toBe([])
            ->and($this->links->saved)->toBe([])
            ->and($this->connections->updated)->toBe([]);
    });

    it('keeps the link of an active appointment while its calendar needs reconnecting, so reconnecting does not publish a duplicate', function () {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(IntegrationsFixtures::awaitingReconnect());
        $this->links->store(IntegrationsFixtures::link());

        ($this->reconcile)();

        expect($this->links->deleted)->toBe([])
            ->and($this->links->all())->toHaveCount(1)
            ->and($this->publisher->withdrawn)->toBe([]);
    });

    it('neither calls the provider nor rewrites anything while the calendar holding the link needs reconnecting', function () {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(IntegrationsFixtures::awaitingReconnect());
        $this->links->store(IntegrationsFixtures::link());

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->publisher->published)->toBe([])
            ->and($this->links->saved)->toBe([])
            ->and($this->connections->updated)->toBe([]);
    });

    it('looks the calendar of the link up inside the business of the link', function () {
        $this->connections = new FakeCalendarConnectionRepository($this->journal);
        $this->links->store(IntegrationsFixtures::link());

        ($this->reconcile)();

        expect($this->connections->businessLookups)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'id' => IntegrationsFixtures::CONNECTION_ID,
        ]]);
    });

    it('does not publish to a calendar the staff identity holds in another business', function () {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))
            ->store(IntegrationsFixtures::connection(businessId: IntegrationsFixtures::OTHER_BUSINESS_ID));

        ($this->reconcile)();

        expect($this->publisher->published)->toBe([])
            ->and($this->links->saved)->toBe([]);
    });

    it('forgets a link whose calendar was disconnected without calling the provider', function () {
        $this->connections = new FakeCalendarConnectionRepository($this->journal);
        $this->links->store(IntegrationsFixtures::link());

        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([])
            ->and($this->links->all())->toBe([]);
    });
});

describe('an appointment reassigned to another staff member', function () {
    beforeEach(function () {
        $this->links->store(IntegrationsFixtures::link());
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));
        ($this->reassignTo)(IntegrationsFixtures::SECOND_STAFF_MEMBER_ID);
    });

    it('withdraws the event from the calendar of the previous staff member', function () {
        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([[
            'connectionId' => IntegrationsFixtures::CONNECTION_ID,
            'externalEventId' => IntegrationsFixtures::EXTERNAL_EVENT_ID,
        ]]);
    });

    it('forgets the link to the previous calendar', function () {
        ($this->reconcile)();

        expect($this->links->deleted)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'id' => IntegrationsFixtures::LINK_ID,
        ]]);
    });

    it('publishes a new event to the calendar of the new staff member', function () {
        ($this->reconcile)();

        expect($this->publisher->published)->toHaveCount(1)
            ->and($this->publisher->published[0]['connectionId'])->toBe(IntegrationsFixtures::SECOND_CONNECTION_ID)
            ->and($this->publisher->published[0]['knownEventId'])->toBeNull();
    });

    it('remembers the new event against the new calendar', function () {
        $this->publisher->answerWith(IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID);

        ($this->reconcile)();

        expect(array_map(static fn ($link) => [$link->id, $link->connectionId, $link->externalEventId()], $this->links->all()))
            ->toBe([[IntegrationsFixtures::NEW_LINK_ID, IntegrationsFixtures::SECOND_CONNECTION_ID, IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID]]);
    });

    it('withdraws the old event before it publishes the new one', function () {
        ($this->reconcile)();

        expect(array_search('publisher.withdraw', $this->journal->entries, true))
            ->toBeLessThan(array_search('publisher.publish', $this->journal->entries, true));
    });

    it('only withdraws when the new staff member has no calendar', function () {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(IntegrationsFixtures::connection());

        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toHaveCount(1)
            ->and($this->publisher->published)->toBe([])
            ->and($this->links->all())->toBe([]);
    });

    it('keeps the link to a previous calendar that needs reconnecting, and remembers the new one beside it', function () {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(
            IntegrationsFixtures::awaitingReconnect(),
            IntegrationsFixtures::connection(
                id: IntegrationsFixtures::SECOND_CONNECTION_ID,
                staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
            ),
        );

        ($this->reconcile)();

        expect($this->links->deleted)->toBe([])
            ->and($this->connections->updated)->toBe([])
            ->and(array_map(static fn ($link) => [$link->id, $link->connectionId], $this->links->all()))->toBe([
                [IntegrationsFixtures::LINK_ID, IntegrationsFixtures::CONNECTION_ID],
                [IntegrationsFixtures::NEW_LINK_ID, IntegrationsFixtures::SECOND_CONNECTION_ID],
            ]);
    });

    it('does not call the provider for a previous calendar that needs reconnecting, and still publishes the new one', function () {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(
            IntegrationsFixtures::awaitingReconnect(),
            IntegrationsFixtures::connection(
                id: IntegrationsFixtures::SECOND_CONNECTION_ID,
                staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
            ),
        );

        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([])
            ->and($this->publisher->published[0]['connectionId'])->toBe(IntegrationsFixtures::SECOND_CONNECTION_ID);
    });
});

dataset('appointments that left the agenda', [
    'cancelled' => AppointmentLifecycle::Cancelled,
    'deleted, or its business closed' => AppointmentLifecycle::Deleted,
]);

describe('an appointment that left the agenda', function () {
    beforeEach(function () {
        $this->links->store(IntegrationsFixtures::link());
    });

    it('withdraws the event from the calendar', function (AppointmentLifecycle $lifecycle) {
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: $lifecycle));

        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([[
            'connectionId' => IntegrationsFixtures::CONNECTION_ID,
            'externalEventId' => IntegrationsFixtures::EXTERNAL_EVENT_ID,
        ]]);
    })->with('appointments that left the agenda');

    it('forgets the link', function (AppointmentLifecycle $lifecycle) {
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: $lifecycle));

        ($this->reconcile)();

        expect($this->links->all())->toBe([])
            ->and($this->links->deleted)->toBe([[
                'businessId' => IntegrationsFixtures::BUSINESS_ID,
                'id' => IntegrationsFixtures::LINK_ID,
            ]]);
    })->with('appointments that left the agenda');

    it('publishes nothing and remembers nothing', function (AppointmentLifecycle $lifecycle) {
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: $lifecycle));

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->publisher->published)->toBe([])
            ->and($this->links->saved)->toBe([])
            ->and($this->connections->staffMemberLookups)->toBe([]);
    })->with('appointments that left the agenda');

    it('does nothing at all when it was never published', function (AppointmentLifecycle $lifecycle) {
        $this->links = new FakeCalendarEventLinkRepository($this->journal);
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: $lifecycle));

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->publisher->withdrawn)->toBe([])
            ->and($this->publisher->published)->toBe([])
            ->and($this->links->deleted)->toBe([]);
    })->with('appointments that left the agenda');

    it('keeps the link untouched while its calendar needs reconnecting', function (AppointmentLifecycle $lifecycle) {
        $this->connections = (new FakeCalendarConnectionRepository($this->journal))->store(IntegrationsFixtures::awaitingReconnect());
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: $lifecycle));

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->publisher->withdrawn)->toBe([])
            ->and($this->publisher->published)->toBe([])
            ->and($this->links->deleted)->toBe([])
            ->and($this->links->saved)->toBe([])
            ->and($this->connections->updated)->toBe([])
            ->and(array_map(static fn ($link) => $link->id, $this->links->all()))->toBe([IntegrationsFixtures::LINK_ID]);
    })->with('appointments that left the agenda');

    it('forgets the link without calling the provider when its calendar was disconnected', function (AppointmentLifecycle $lifecycle) {
        $this->connections = new FakeCalendarConnectionRepository($this->journal);
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: $lifecycle));

        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([])
            ->and($this->links->all())->toBe([]);
    })->with('appointments that left the agenda');

    it('is a no-op on a second run once the event is withdrawn', function (AppointmentLifecycle $lifecycle) {
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: $lifecycle));

        ($this->reconcile)();
        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toHaveCount(1)
            ->and($this->links->deleted)->toHaveCount(1);
    })->with('appointments that left the agenda');
});

describe('the provider revoked the authorization', function () {
    it('marks the calendar as needing reconnection when publishing is refused', function () {
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        ($this->reconcile)();

        expect($this->connections->updated)->toBe([[
            'id' => IntegrationsFixtures::CONNECTION_ID,
            'status' => ConnectionStatus::NeedsReconnect,
        ]])->and($this->connections->stored(IntegrationsFixtures::CONNECTION_ID)->status())->toBe(ConnectionStatus::NeedsReconnect);
    });

    it('answers success and remembers no link when publishing is refused', function () {
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->links->saved)->toBe([]);
    });

    it('keeps the existing link untouched when patching is refused', function () {
        $this->links->store(IntegrationsFixtures::link());
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        ($this->reconcile)();

        expect($this->links->deleted)->toBe([])
            ->and($this->links->saved)->toBe([])
            ->and($this->links->all()[0]->externalEventId())->toBe(IntegrationsFixtures::EXTERNAL_EVENT_ID);
    });

    it('marks the calendar as needing reconnection when withdrawing is refused', function () {
        $this->links->store(IntegrationsFixtures::link());
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: AppointmentLifecycle::Cancelled));
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        $response = ($this->reconcile)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->connections->updated)->toBe([[
                'id' => IntegrationsFixtures::CONNECTION_ID,
                'status' => ConnectionStatus::NeedsReconnect,
            ]]);
    });

    it('keeps the link when withdrawing is refused', function () {
        $this->links->store(IntegrationsFixtures::link());
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: AppointmentLifecycle::Cancelled));
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        ($this->reconcile)();

        expect($this->links->deleted)->toBe([])
            ->and(array_map(static fn ($link) => $link->id, $this->links->all()))->toBe([IntegrationsFixtures::LINK_ID]);
    });

    it('still withdraws the other links when one calendar refuses the withdrawal', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));
        $this->links->store(
            IntegrationsFixtures::link(),
            IntegrationsFixtures::link(
                id: IntegrationsFixtures::SECOND_LINK_ID,
                connectionId: IntegrationsFixtures::SECOND_CONNECTION_ID,
                externalEventId: IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID,
            ),
        );
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: AppointmentLifecycle::Cancelled));
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        ($this->reconcile)();

        expect($this->publisher->withdrawn)->toBe([[
            'connectionId' => IntegrationsFixtures::SECOND_CONNECTION_ID,
            'externalEventId' => IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID,
        ]])->and($this->links->deleted)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'id' => IntegrationsFixtures::SECOND_LINK_ID,
        ]])->and(array_map(static fn ($link) => $link->id, $this->links->all()))->toBe([IntegrationsFixtures::LINK_ID])
            ->and($this->connections->updated)->toBe([[
                'id' => IntegrationsFixtures::CONNECTION_ID,
                'status' => ConnectionStatus::NeedsReconnect,
            ]]);
    });

    it('keeps the link to the previous calendar when it refuses the withdrawal during a reassignment', function () {
        $this->links->store(IntegrationsFixtures::link());
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));
        ($this->reassignTo)(IntegrationsFixtures::SECOND_STAFF_MEMBER_ID);
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        ($this->reconcile)();

        expect($this->links->deleted)->toBe([])
            ->and(array_map(static fn ($link) => [$link->id, $link->connectionId], $this->links->all()))->toBe([
                [IntegrationsFixtures::LINK_ID, IntegrationsFixtures::CONNECTION_ID],
                [IntegrationsFixtures::NEW_LINK_ID, IntegrationsFixtures::SECOND_CONNECTION_ID],
            ]);
    });

    it('still publishes to the new calendar when the previous one refuses the withdrawal', function () {
        $this->links->store(IntegrationsFixtures::link());
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));
        ($this->reassignTo)(IntegrationsFixtures::SECOND_STAFF_MEMBER_ID);
        $this->publisher->revokeFor(IntegrationsFixtures::CONNECTION_ID);

        ($this->reconcile)();

        expect($this->connections->stored(IntegrationsFixtures::CONNECTION_ID)->status())->toBe(ConnectionStatus::NeedsReconnect)
            ->and($this->connections->stored(IntegrationsFixtures::SECOND_CONNECTION_ID)->status())->toBe(ConnectionStatus::Connected)
            ->and($this->publisher->published[0]['connectionId'])->toBe(IntegrationsFixtures::SECOND_CONNECTION_ID)
            ->and($this->links->saved[0]['connectionId'])->toBe(IntegrationsFixtures::SECOND_CONNECTION_ID);
    });
});

describe('an unexpected failure while withdrawing', function () {
    beforeEach(function () {
        $this->unexpected = new RuntimeException('The calendar provider timed out.');
        $this->links->store(IntegrationsFixtures::link());
        $this->appointments->add(IntegrationsFixtures::snapshot(lifecycle: AppointmentLifecycle::Cancelled));
        $this->publisher->failWithdrawingWith($this->unexpected);
    });

    it('lets the failure escape unchanged', function () {
        expect(fn () => ($this->reconcile)())
            ->toThrow(fn (RuntimeException $thrown) => expect($thrown)->toBe($this->unexpected));
    });

    it('keeps the link and leaves the connection as it was', function () {
        expect(fn () => ($this->reconcile)())->toThrow(RuntimeException::class);

        expect($this->links->deleted)->toBe([])
            ->and($this->links->all())->toHaveCount(1)
            ->and($this->connections->updated)->toBe([]);
    });
});

describe('an appointment that cannot be found', function () {
    it('refuses with appointment not found', function () {
        $response = ($this->reconcile)(IntegrationsFixtures::SECOND_APPOINTMENT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('touches no link, no connection and no calendar', function () {
        ($this->reconcile)(IntegrationsFixtures::SECOND_APPOINTMENT_ID);

        expect($this->journal->entries)->toBe(['appointments.snapshotOf']);
    });
});

describe('a provider answer that points to no event', function () {
    it('refuses with an invalid calendar event link and remembers nothing', function () {
        $this->publisher->answerWith('   ');

        $response = ($this->reconcile)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_calendar_event_link')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->links->saved)->toBe([]);
    });
});
