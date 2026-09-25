<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\DisconnectCalendarInput;
use App\Domains\Integrations\Application\UseCases\DisconnectCalendar;
use App\Domains\Integrations\Events\CalendarDisconnected;
use App\Domains\Integrations\Exceptions\CalendarConnectionNotFound;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeTransactionManager;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarEventLinkRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarOwners;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsJournal;

beforeEach(function () {
    $this->journal = new IntegrationsJournal;
    $this->transactions = new FakeTransactionManager;
    $this->owners = (new FakeCalendarOwners($this->journal))
        ->member(IntegrationsFixtures::BUSINESS_ID, IntegrationsFixtures::ACCOUNT_ID, IntegrationsFixtures::STAFF_MEMBER_ID);
    $this->connections = new FakeCalendarConnectionRepository($this->journal, $this->transactions);
    $this->links = new FakeCalendarEventLinkRepository($this->journal, $this->transactions);

    $this->dispatched = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): array {
        $this->dispatched[] = $event;
        $this->journal->record('events.dispatch');

        return [];
    });

    $this->disconnect = fn (string $accountId = IntegrationsFixtures::ACCOUNT_ID) => (new DisconnectCalendar(
        $this->owners,
        $this->connections,
        $this->links,
        $this->transactions,
        new FakeBusinessContext,
        $this->events,
    ))->handle(new DisconnectCalendarInput($accountId));
});

describe('disconnecting the caller calendar', function () {
    beforeEach(function () {
        $this->connections->store(IntegrationsFixtures::connection());
        $this->links->store(
            IntegrationsFixtures::link(),
            IntegrationsFixtures::link(
                id: IntegrationsFixtures::SECOND_LINK_ID,
                appointmentId: IntegrationsFixtures::SECOND_APPOINTMENT_ID,
                externalEventId: IntegrationsFixtures::SECOND_EXTERNAL_EVENT_ID,
            ),
        );
    });

    it('answers with an empty success', function () {
        $response = ($this->disconnect)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('removes the connection', function () {
        ($this->disconnect)();

        expect($this->connections->stored(IntegrationsFixtures::CONNECTION_ID))->toBeNull()
            ->and($this->connections->deleted)->toHaveCount(1)
            ->and($this->connections->deleted[0]['businessId'])->toBe(IntegrationsFixtures::BUSINESS_ID)
            ->and($this->connections->deleted[0]['id'])->toBe(IntegrationsFixtures::CONNECTION_ID);
    });

    it('forgets every event link of the connection', function () {
        ($this->disconnect)();

        expect($this->links->all())->toBe([])
            ->and($this->links->deletedForConnection)->toHaveCount(1)
            ->and($this->links->deletedForConnection[0]['businessId'])->toBe(IntegrationsFixtures::BUSINESS_ID)
            ->and($this->links->deletedForConnection[0]['connectionId'])->toBe(IntegrationsFixtures::CONNECTION_ID);
    });

    it('removes the links and the connection inside one transaction', function () {
        ($this->disconnect)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->links->deletedForConnection[0]['insideTransaction'])->toBeTrue()
            ->and($this->connections->deleted[0]['insideTransaction'])->toBeTrue();
    });

    it('removes the links before the connection they point to', function () {
        ($this->disconnect)();

        expect(array_search('links.deleteForConnection', $this->journal->entries, true))
            ->toBeLessThan(array_search('connections.delete', $this->journal->entries, true));
    });

    it('announces the disconnection exactly once, carrying the connection and business uuids', function () {
        ($this->disconnect)();

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(CalendarDisconnected::class)
            ->and($this->dispatched[0]->connectionId)->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($this->dispatched[0]->businessId)->toBe(IntegrationsFixtures::BUSINESS_ID);
    });

    it('announces only after the transaction has committed', function () {
        ($this->disconnect)();

        expect($this->journal->entries)->toBe([
            'owners.staffMemberIdOf',
            'connections.findForStaffMember',
            'links.deleteForConnection',
            'connections.delete',
            'events.dispatch',
        ]);
    });

    it('looks the connection up for the caller staff member, in the business in context, with google', function () {
        ($this->disconnect)();

        expect($this->owners->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => IntegrationsFixtures::ACCOUNT_ID,
        ]])->and($this->connections->staffMemberLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffMemberId' => IntegrationsFixtures::STAFF_MEMBER_ID,
            'provider' => CalendarProvider::Google,
        ]]);
    });
});

describe('a connection that needs reconnecting', function () {
    it('can still be disconnected', function () {
        $this->connections->store(IntegrationsFixtures::awaitingReconnect());

        expect(($this->disconnect)()->succeeded())->toBeTrue()
            ->and($this->connections->stored(IntegrationsFixtures::CONNECTION_ID))->toBeNull()
            ->and($this->dispatched)->toHaveCount(1);
    });
});

describe('connections of other people', function () {
    it('leaves the connection of a colleague and its links in place', function () {
        $this->connections->store(
            IntegrationsFixtures::connection(),
            IntegrationsFixtures::connection(
                id: IntegrationsFixtures::SECOND_CONNECTION_ID,
                staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
            ),
        );
        $this->links->store(IntegrationsFixtures::link(
            id: IntegrationsFixtures::SECOND_LINK_ID,
            connectionId: IntegrationsFixtures::SECOND_CONNECTION_ID,
        ));

        ($this->disconnect)();

        expect($this->connections->stored(IntegrationsFixtures::SECOND_CONNECTION_ID))->not->toBeNull()
            ->and($this->links->all())->toHaveCount(1)
            ->and($this->links->all()[0]->id)->toBe(IntegrationsFixtures::SECOND_LINK_ID);
    });

    it('refuses when only a colleague is connected', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));

        $response = ($this->disconnect)();

        expect($response->error()->code)->toBe('calendar_connection_not_found')
            ->and($this->connections->stored(IntegrationsFixtures::SECOND_CONNECTION_ID))->not->toBeNull();
    });

    it('refuses when the same staff identity is connected only in another business', function () {
        $this->connections->store(IntegrationsFixtures::connection(businessId: IntegrationsFixtures::OTHER_BUSINESS_ID));

        $response = ($this->disconnect)();

        expect($response->error()->code)->toBe('calendar_connection_not_found')
            ->and($this->connections->stored(IntegrationsFixtures::CONNECTION_ID))->not->toBeNull();
    });
});

describe('nothing to disconnect', function () {
    it('refuses with calendar connection not found', function () {
        $response = ($this->disconnect)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('calendar_connection_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('opens no transaction, deletes nothing and announces nothing', function () {
        ($this->disconnect)();

        expect($this->transactions->runs())->toBe(0)
            ->and($this->links->deletedForConnection)->toBe([])
            ->and($this->connections->deleted)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });
});

describe('a caller who is no staff member of the business', function () {
    it('refuses with staff member not found and touches nothing else', function () {
        $this->connections->store(IntegrationsFixtures::connection());

        $response = ($this->disconnect)(IntegrationsFixtures::OTHER_ACCOUNT_ID);

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe(['owners.staffMemberIdOf'])
            ->and($this->dispatched)->toBe([]);
    });
});

describe('a connection removed concurrently', function () {
    it('answers with the refusal of the repository and announces nothing', function () {
        $this->connections->store(IntegrationsFixtures::connection());
        $this->connections->failDeletingWith(CalendarConnectionNotFound::withId(IntegrationsFixtures::CONNECTION_ID));

        $response = ($this->disconnect)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('calendar_connection_not_found')
            ->and($this->dispatched)->toBe([]);
    });
});
