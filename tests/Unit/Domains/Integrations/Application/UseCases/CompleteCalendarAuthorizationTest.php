<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\CalendarConnectionData;
use App\Domains\Integrations\Application\UseCases\CompleteCalendarAuthorization;
use App\Domains\Integrations\Events\CalendarConnected;
use App\Domains\Integrations\Exceptions\CalendarAlreadyConnected;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarScopeNotGranted;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeBusinessProfiles;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarAuthorizationStates;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarAuthorizer;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarOwners;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarProvisioning;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsJournal;

beforeEach(function () {
    $this->journal = new IntegrationsJournal;
    $this->states = (new FakeCalendarAuthorizationStates($this->journal))
        ->remember(IntegrationsFixtures::STATE, IntegrationsFixtures::pending());
    $this->owners = (new FakeCalendarOwners($this->journal))
        ->member(IntegrationsFixtures::BUSINESS_ID, IntegrationsFixtures::ACCOUNT_ID, IntegrationsFixtures::STAFF_MEMBER_ID);
    $this->authorizer = new FakeCalendarAuthorizer($this->journal);
    $this->businesses = (new FakeBusinessProfiles($this->journal))
        ->add(IntegrationsFixtures::BUSINESS_ID, IntegrationsFixtures::profile());
    $this->provisioning = new FakeCalendarProvisioning($this->journal);
    $this->connections = new FakeCalendarConnectionRepository($this->journal);
    $this->input = IntegrationsFixtures::completeInput();

    $this->dispatched = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): array {
        $this->dispatched[] = $event;
        $this->journal->record('events.dispatch');

        return [];
    });

    $this->reported = [];
    $this->compensationFailures = Mockery::mock(ExceptionHandler::class);
    $this->compensationFailures->shouldReceive('report')->andReturnUsing(function (Throwable $failure): void {
        $this->reported[] = $failure;
    });

    $this->complete = fn () => (new CompleteCalendarAuthorization(
        $this->states,
        $this->owners,
        $this->authorizer,
        $this->businesses,
        $this->provisioning,
        $this->connections,
        new FixedIdGenerator(IntegrationsFixtures::NEW_CONNECTION_ID),
        new FakeClock(IntegrationsFixtures::now()),
        $this->events,
        $this->compensationFailures,
    ))->handle($this->input);
});

describe('a first connection', function () {
    it('answers with the new connection, field by field', function () {
        $data = ($this->complete)()->value();

        expect($data)->toBeInstanceOf(CalendarConnectionData::class)
            ->and($data->id)->toBe(IntegrationsFixtures::NEW_CONNECTION_ID)
            ->and($data->status)->toBe(ConnectionStatus::Connected)
            ->and($data->accountEmail)->toBe(IntegrationsFixtures::ACCOUNT_EMAIL)
            ->and($data->connectedAt)->toEqual(IntegrationsFixtures::now());
    });

    it('saves the connection exactly once, with the tokens of the grant', function () {
        ($this->complete)();

        expect($this->connections->savedWithTokens)->toHaveCount(1)
            ->and($this->connections->savedWithTokens[0]['tokens'])->toEqual(IntegrationsFixtures::tokens());
    });

    it('scopes the connection to the business and staff member the state was issued for', function () {
        ($this->complete)();

        $saved = $this->connections->savedWithTokens[0]['connection'];

        expect($saved->businessId)->toBe(IntegrationsFixtures::BUSINESS_ID)
            ->and($saved->staffMemberId)->toBe(IntegrationsFixtures::STAFF_MEMBER_ID)
            ->and($saved->provider)->toBe(CalendarProvider::Google);
    });

    it('takes the business from the state, not from any ambient context', function () {
        $this->states->remember('other-business-state', IntegrationsFixtures::pending(
            businessId: IntegrationsFixtures::OTHER_BUSINESS_ID,
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));
        $this->owners->member(
            IntegrationsFixtures::OTHER_BUSINESS_ID,
            IntegrationsFixtures::ACCOUNT_ID,
            IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        );
        $this->businesses->add(IntegrationsFixtures::OTHER_BUSINESS_ID, IntegrationsFixtures::profile());
        $this->input = IntegrationsFixtures::completeInput(state: 'other-business-state');

        ($this->complete)();

        $saved = $this->connections->savedWithTokens[0]['connection'];

        expect($saved->businessId)->toBe(IntegrationsFixtures::OTHER_BUSINESS_ID)
            ->and($saved->staffMemberId)->toBe(IntegrationsFixtures::SECOND_STAFF_MEMBER_ID)
            ->and($this->businesses->lookups)->toBe([IntegrationsFixtures::OTHER_BUSINESS_ID]);
    });

    it('creates the dedicated calendar from the grant and the business profile', function () {
        ($this->complete)();

        expect($this->provisioning->created)->toHaveCount(1)
            ->and($this->provisioning->created[0]['grant'])->toEqual(IntegrationsFixtures::grant())
            ->and($this->provisioning->created[0]['business'])->toEqual(IntegrationsFixtures::profile())
            ->and($this->provisioning->adopted)->toBe([]);
    });

    it('points the connection at the calendar it just created', function () {
        $this->provisioning->createWith(IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID);

        ($this->complete)();

        expect($this->connections->savedWithTokens[0]['connection']->externalCalendarId())
            ->toBe(IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID);
    });

    it('trims the account email the provider disclosed', function () {
        $this->authorizer->grantWith(IntegrationsFixtures::grant('  '.IntegrationsFixtures::ACCOUNT_EMAIL.'  '));

        expect(($this->complete)()->value()->accountEmail)->toBe(IntegrationsFixtures::ACCOUNT_EMAIL);
    });

    it('exchanges the code the callback carried, once', function () {
        ($this->complete)();

        expect($this->authorizer->exchangedCodes)->toBe([IntegrationsFixtures::CODE]);
    });

    it('consumes the state it was handed, once', function () {
        ($this->complete)();

        expect($this->states->consumed)->toBe([IntegrationsFixtures::STATE]);
    });

    it('announces the connection exactly once, carrying the connection and business uuids', function () {
        ($this->complete)();

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(CalendarConnected::class)
            ->and($this->dispatched[0]->connectionId)->toBe(IntegrationsFixtures::NEW_CONNECTION_ID)
            ->and($this->dispatched[0]->businessId)->toBe(IntegrationsFixtures::BUSINESS_ID);
    });

    it('compensates nothing and reports nothing', function () {
        ($this->complete)();

        expect($this->provisioning->discarded)->toBe([])
            ->and($this->provisioning->revokedGrants)->toBe([])
            ->and($this->connections->accountLookups)->toBe([])
            ->and($this->reported)->toBe([]);
    });

    it('creates the calendar, then saves, then announces', function () {
        ($this->complete)();

        $entries = $this->journal->entries;

        expect(array_search('provisioning.createCalendar', $entries, true))
            ->toBeLessThan(array_search('connections.saveWithTokens', $entries, true))
            ->and(array_search('connections.saveWithTokens', $entries, true))
            ->toBeLessThan(array_search('events.dispatch', $entries, true));
    });
});

describe('reconnecting a connection that needs it', function () {
    beforeEach(function () {
        $this->connections->store(IntegrationsFixtures::awaitingReconnect());
        $this->authorizer->grantWith(IntegrationsFixtures::grant(IntegrationsFixtures::NEW_ACCOUNT_EMAIL));
    });

    it('revives the existing connection instead of creating a second one', function () {
        $data = ($this->complete)()->value();

        expect($data->id)->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($data->status)->toBe(ConnectionStatus::Connected)
            ->and($data->accountEmail)->toBe(IntegrationsFixtures::NEW_ACCOUNT_EMAIL)
            ->and($data->connectedAt)->toEqual(IntegrationsFixtures::now());
    });

    it('saves the revived connection with the fresh tokens', function () {
        ($this->complete)();

        expect($this->connections->savedWithTokens)->toHaveCount(1)
            ->and($this->connections->savedWithTokens[0]['connection']->id)->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($this->connections->savedWithTokens[0]['status'])->toBe(ConnectionStatus::Connected)
            ->and($this->connections->savedWithTokens[0]['tokens'])->toEqual(IntegrationsFixtures::tokens());
    });

    it('adopts the calendar the connection already published to, and creates none', function () {
        ($this->complete)();

        expect($this->provisioning->adopted)->toHaveCount(1)
            ->and($this->provisioning->adopted[0]['externalCalendarId'])->toBe(IntegrationsFixtures::EXTERNAL_CALENDAR_ID)
            ->and($this->provisioning->adopted[0]['business'])->toEqual(IntegrationsFixtures::profile())
            ->and($this->provisioning->created)->toBe([]);
    });

    it('repoints the connection when adopting had to provision a replacement calendar', function () {
        $this->provisioning->adoptAs(IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID);

        ($this->complete)();

        expect($this->connections->savedWithTokens[0]['connection']->externalCalendarId())
            ->toBe(IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID);
    });

    it('announces the reconnection with the uuid of the existing connection', function () {
        ($this->complete)();

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(CalendarConnected::class)
            ->and($this->dispatched[0]->connectionId)->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($this->dispatched[0]->businessId)->toBe(IntegrationsFixtures::BUSINESS_ID);
    });
});

dataset('calendar authorization refusals', [
    'blank state' => [
        fn (object $test) => $test->input = IntegrationsFixtures::completeInput(state: '   '),
        'calendar_authorization_state_invalid',
        DomainFailureKind::Invalid,
    ],
    'expired or already used state' => [
        fn (object $test) => $test->input = IntegrationsFixtures::completeInput(state: 'unknown-state'),
        'calendar_authorization_state_invalid',
        DomainFailureKind::Invalid,
    ],
    'state issued to another account' => [
        fn (object $test) => $test->input = IntegrationsFixtures::completeInput(accountId: IntegrationsFixtures::OTHER_ACCOUNT_ID),
        'calendar_authorization_state_invalid',
        DomainFailureKind::Invalid,
    ],
    'account now holding another staff membership' => [
        fn (object $test) => $test->owners->member(
            IntegrationsFixtures::BUSINESS_ID,
            IntegrationsFixtures::ACCOUNT_ID,
            IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ),
        'calendar_authorization_state_invalid',
        DomainFailureKind::Invalid,
    ],
    'account no longer a staff member' => [
        fn (object $test) => $test->owners = new FakeCalendarOwners($test->journal),
        'staff_member_not_found',
        DomainFailureKind::NotFound,
    ],
    'user denied the consent screen' => [
        fn (object $test) => $test->input = IntegrationsFixtures::completeInput(code: '', error: 'access_denied'),
        'calendar_authorization_denied',
        DomainFailureKind::Invalid,
    ],
    'provider answered with an error' => [
        fn (object $test) => $test->input = IntegrationsFixtures::completeInput(code: '', error: 'server_error'),
        'calendar_authorization_failed',
        DomainFailureKind::Invalid,
    ],
    'callback carried no code' => [
        fn (object $test) => $test->input = IntegrationsFixtures::completeInput(code: ''),
        'calendar_authorization_failed',
        DomainFailureKind::Invalid,
    ],
    'code exchange failed' => [
        fn (object $test) => $test->authorizer->failExchangeWith(CalendarAuthorizationFailed::exchangeFailed()),
        'calendar_authorization_failed',
        DomainFailureKind::Invalid,
    ],
    'calendar scope not granted' => [
        fn (object $test) => $test->authorizer->failExchangeWith(
            CalendarScopeNotGranted::forScope('https://www.googleapis.com/auth/calendar.app.created'),
        ),
        'calendar_scope_not_granted',
        DomainFailureKind::Invalid,
    ],
    'business no longer exists' => [
        fn (object $test) => $test->businesses = new FakeBusinessProfiles($test->journal),
        'business_not_found',
        DomainFailureKind::NotFound,
    ],
    'calendar already connected' => [
        fn (object $test) => $test->connections->store(IntegrationsFixtures::connection()),
        'calendar_already_connected',
        DomainFailureKind::Conflict,
    ],
    'a concurrent connection won the save' => [
        fn (object $test) => $test->connections->failSavingWith(
            CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID),
        ),
        'calendar_already_connected',
        DomainFailureKind::Conflict,
    ],
    'dedicated calendar could not be created' => [
        fn (object $test) => $test->provisioning->failWith(CalendarAuthorizationFailed::calendarNotProvisioned()),
        'calendar_authorization_failed',
        DomainFailureKind::Invalid,
    ],
    'grant disclosed a blank account email' => [
        fn (object $test) => $test->authorizer->grantWith(IntegrationsFixtures::grant('   ')),
        'invalid_calendar_connection',
        DomainFailureKind::Invalid,
    ],
    'provisioning answered a blank calendar id' => [
        fn (object $test) => $test->provisioning->createWith('   '),
        'invalid_calendar_connection',
        DomainFailureKind::Invalid,
    ],
]);

describe('the refusals', function () {
    it('answers with the failure code and kind', function (Closure $arrange, string $code, DomainFailureKind $kind) {
        $arrange($this);

        $response = ($this->complete)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind);
    })->with('calendar authorization refusals');

    it('leaves no connection behind', function (Closure $arrange) {
        $arrange($this);

        ($this->complete)();

        expect($this->connections->savedWithTokens)->toBe([])
            ->and($this->connections->stored(IntegrationsFixtures::NEW_CONNECTION_ID))->toBeNull();
    })->with('calendar authorization refusals');

    it('announces nothing', function (Closure $arrange) {
        $arrange($this);

        ($this->complete)();

        expect($this->dispatched)->toBe([]);
    })->with('calendar authorization refusals');
});

describe('the staff membership the state was bound to', function () {
    it('refuses when the account now holds a different staff membership in that business', function () {
        $this->owners->member(
            IntegrationsFixtures::BUSINESS_ID,
            IntegrationsFixtures::ACCOUNT_ID,
            IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        );

        $response = ($this->complete)();

        expect($response->error()->code)->toBe('calendar_authorization_state_invalid')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->provisioning->created)->toBe([])
            ->and($this->provisioning->adopted)->toBe([])
            ->and($this->connections->savedWithTokens)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });

    it('refuses when the account is no longer a staff member of that business', function () {
        $this->owners = (new FakeCalendarOwners($this->journal))->member(
            IntegrationsFixtures::OTHER_BUSINESS_ID,
            IntegrationsFixtures::ACCOUNT_ID,
            IntegrationsFixtures::STAFF_MEMBER_ID,
        );

        $response = ($this->complete)();

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->provisioning->created)->toBe([])
            ->and($this->provisioning->adopted)->toBe([])
            ->and($this->connections->savedWithTokens)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });

    it('asks for the membership in the business and account the state carries', function () {
        ($this->complete)();

        expect($this->owners->lookups)->toBe([[
            'businessId' => IntegrationsFixtures::BUSINESS_ID,
            'accountId' => IntegrationsFixtures::ACCOUNT_ID,
        ]]);
    });
});

describe('the order of the guards', function () {
    it('provisions no calendar whenever it refuses', function (Closure $arrange) {
        $arrange($this);

        ($this->complete)();

        expect($this->provisioning->created)->toBe([])
            ->and($this->provisioning->adopted)->toBe([]);
    })->with([
        'blank state' => [fn (object $test) => $test->input = IntegrationsFixtures::completeInput(state: '')],
        'state of another account' => [fn (object $test) => $test->input = IntegrationsFixtures::completeInput(accountId: IntegrationsFixtures::OTHER_ACCOUNT_ID)],
        'user denied' => [fn (object $test) => $test->input = IntegrationsFixtures::completeInput(code: '', error: 'access_denied')],
        'code exchange failed' => [fn (object $test) => $test->authorizer->failExchangeWith(CalendarAuthorizationFailed::exchangeFailed())],
        'business gone' => [fn (object $test) => $test->businesses = new FakeBusinessProfiles($test->journal)],
        'already connected' => [fn (object $test) => $test->connections->store(IntegrationsFixtures::connection())],
    ]);

    it('refuses a malformed callback before it consumes the state or calls the provider', function () {
        $this->input = IntegrationsFixtures::completeInput(code: '', error: 'access_denied');

        ($this->complete)();

        expect($this->journal->entries)->toBe([]);
    });

    it('refuses a state issued to another account before it exchanges the code', function () {
        $this->input = IntegrationsFixtures::completeInput(accountId: IntegrationsFixtures::OTHER_ACCOUNT_ID);

        ($this->complete)();

        expect($this->authorizer->exchangedCodes)->toBe([]);
    });

    it('refuses a former staff member before it exchanges the code', function () {
        $this->owners = new FakeCalendarOwners($this->journal);

        ($this->complete)();

        expect($this->authorizer->exchangedCodes)->toBe([]);
    });

    it('refuses an already connected staff member before it reads the business or exchanges the code', function () {
        $this->connections->store(IntegrationsFixtures::connection());

        ($this->complete)();

        expect($this->authorizer->exchangedCodes)->toBe([])
            ->and($this->businesses->lookups)->toBe([])
            ->and($this->journal->entries)->toBe([
                'states.consume',
                'owners.staffMemberIdOf',
                'connections.findForStaffMember',
            ]);
    });

    it('refuses a business that no longer exists before it exchanges the code', function () {
        $this->businesses = new FakeBusinessProfiles($this->journal);

        ($this->complete)();

        expect($this->authorizer->exchangedCodes)->toBe([]);
    });

    it('refuses a blank account email before it provisions any calendar', function () {
        $this->authorizer->grantWith(IntegrationsFixtures::grant('   '));

        ($this->complete)();

        expect($this->provisioning->created)->toBe([])
            ->and($this->provisioning->adopted)->toBe([])
            ->and(in_array('authorizer.exchange', $this->journal->entries, true))->toBeTrue();
    });

    it('provisions no calendar when the staff member is already connected', function () {
        $this->connections->store(IntegrationsFixtures::connection());

        ($this->complete)();

        expect($this->provisioning->created)->toBe([])
            ->and($this->provisioning->adopted)->toBe([]);
    });

    it('leaves the existing connection untouched when it is already connected', function () {
        $this->connections->store(IntegrationsFixtures::connection());
        $this->authorizer->grantWith(IntegrationsFixtures::grant(IntegrationsFixtures::NEW_ACCOUNT_EMAIL));

        ($this->complete)();

        $stored = $this->connections->stored(IntegrationsFixtures::CONNECTION_ID);

        expect($stored->accountEmail())->toBe(IntegrationsFixtures::ACCOUNT_EMAIL)
            ->and($stored->connectedAt())->toEqual(IntegrationsFixtures::instant(IntegrationsFixtures::CONNECTED_AT));
    });
});

dataset('failures before the exchange', [
    'blank state' => [fn (object $test) => $test->input = IntegrationsFixtures::completeInput(state: '')],
    'expired or already used state' => [fn (object $test) => $test->input = IntegrationsFixtures::completeInput(state: 'unknown-state')],
    'state of another account' => [fn (object $test) => $test->input = IntegrationsFixtures::completeInput(accountId: IntegrationsFixtures::OTHER_ACCOUNT_ID)],
    'account now holding another staff membership' => [fn (object $test) => $test->owners->member(
        IntegrationsFixtures::BUSINESS_ID,
        IntegrationsFixtures::ACCOUNT_ID,
        IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
    )],
    'account no longer a staff member' => [fn (object $test) => $test->owners = new FakeCalendarOwners($test->journal)],
    'user denied' => [fn (object $test) => $test->input = IntegrationsFixtures::completeInput(code: '', error: 'access_denied')],
    'already connected' => [fn (object $test) => $test->connections->store(IntegrationsFixtures::connection())],
    'business gone' => [fn (object $test) => $test->businesses = new FakeBusinessProfiles($test->journal)],
    'code exchange failed' => [fn (object $test) => $test->authorizer->failExchangeWith(CalendarAuthorizationFailed::exchangeFailed())],
]);

describe('a failure before any grant exists', function () {
    it('discards no calendar and revokes no grant', function (Closure $arrange) {
        $arrange($this);

        ($this->complete)();

        expect($this->provisioning->discarded)->toBe([])
            ->and($this->provisioning->revokedGrants)->toBe([])
            ->and($this->connections->accountLookups)->toBe([])
            ->and($this->reported)->toBe([]);
    })->with('failures before the exchange');
});

describe('a first connection that fails after the exchange', function () {
    it('discards the calendar it created when the connection refuses the calendar id', function () {
        $this->provisioning->createWith('   ');

        $response = ($this->complete)();

        expect($response->error()->code)->toBe('invalid_calendar_connection')
            ->and($this->provisioning->discarded)->toEqual([[
                'grant' => IntegrationsFixtures::grant(),
                'externalCalendarId' => '   ',
            ]]);
    });

    it('discards the calendar it created when a concurrent connection won the save', function () {
        $this->provisioning->createWith(IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID);
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));

        $response = ($this->complete)();

        expect($response->error()->code)->toBe('calendar_already_connected')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->provisioning->discarded)->toEqual([[
                'grant' => IntegrationsFixtures::grant(),
                'externalCalendarId' => IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID,
            ]]);
    });

    it('revokes the unused grant', function () {
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));

        ($this->complete)();

        expect($this->provisioning->revokedGrants)->toEqual([IntegrationsFixtures::grant()]);
    });

    it('discards the calendar before it revokes the grant', function () {
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));

        ($this->complete)();

        expect(array_values(array_filter(
            $this->journal->entries,
            static fn (string $entry): bool => in_array($entry, ['provisioning.discardCalendar', 'provisioning.revokeGrant'], true),
        )))->toBe(['provisioning.discardCalendar', 'provisioning.revokeGrant']);
    });

    it('asks whether a live connection still uses the account the grant disclosed', function () {
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));

        ($this->complete)();

        expect($this->connections->accountLookups)->toBe([[
            'provider' => CalendarProvider::Google,
            'accountEmail' => IntegrationsFixtures::ACCOUNT_EMAIL,
        ]]);
    });

    it('keeps the grant while another live connection uses the same account', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));

        ($this->complete)();

        expect($this->provisioning->revokedGrants)->toBe([])
            ->and($this->provisioning->discarded)->toHaveCount(1);
    });

    it('only revokes the grant when the dedicated calendar could not be created', function () {
        $this->provisioning->failWith(CalendarAuthorizationFailed::calendarNotProvisioned());

        $response = ($this->complete)();

        expect($response->error()->code)->toBe('calendar_authorization_failed')
            ->and($this->provisioning->discarded)->toBe([])
            ->and($this->provisioning->revokedGrants)->toEqual([IntegrationsFixtures::grant()]);
    });

    it('only revokes the grant when it disclosed a blank account email', function () {
        $this->authorizer->grantWith(IntegrationsFixtures::grant('   '));

        ($this->complete)();

        expect($this->provisioning->discarded)->toBe([])
            ->and($this->provisioning->revokedGrants)->toEqual([IntegrationsFixtures::grant('   ')]);
    });

    it('reports nothing when every compensation succeeds', function () {
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));

        ($this->complete)();

        expect($this->reported)->toBe([]);
    });
});

describe('a reconnection that fails after the exchange', function () {
    beforeEach(function () {
        $this->connections->store(IntegrationsFixtures::awaitingReconnect());
        $this->authorizer->grantWith(IntegrationsFixtures::grant(IntegrationsFixtures::NEW_ACCOUNT_EMAIL));
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));
    });

    it('answers with the failure of the save', function () {
        $response = ($this->complete)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('calendar_already_connected')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('never discards the existing calendar it adopted unchanged', function () {
        ($this->complete)();

        expect($this->provisioning->discarded)->toBe([]);
    });

    it('discards the replacement calendar adopting provisioned, and only that one', function () {
        $this->provisioning->adoptAs(IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID);

        ($this->complete)();

        expect($this->provisioning->discarded)->toEqual([[
            'grant' => IntegrationsFixtures::grant(IntegrationsFixtures::NEW_ACCOUNT_EMAIL),
            'externalCalendarId' => IntegrationsFixtures::NEW_EXTERNAL_CALENDAR_ID,
        ]]);
    });

    it('revokes the grant of an account no live connection uses', function () {
        ($this->complete)();

        expect($this->provisioning->revokedGrants)->toEqual([IntegrationsFixtures::grant(IntegrationsFixtures::NEW_ACCOUNT_EMAIL)]);
    });

    it('keeps the grant when the connection it was reviving already uses that account', function () {
        $this->authorizer->grantWith(IntegrationsFixtures::grant());

        ($this->complete)();

        expect($this->provisioning->revokedGrants)->toBe([]);
    });

    it('only revokes the grant when the calendar could not be adopted', function () {
        $this->provisioning->failWith(CalendarAuthorizationFailed::calendarNotProvisioned());

        ($this->complete)();

        expect($this->provisioning->discarded)->toBe([])
            ->and($this->provisioning->revokedGrants)->toEqual([IntegrationsFixtures::grant(IntegrationsFixtures::NEW_ACCOUNT_EMAIL)]);
    });
});

dataset('compensations that fail', [
    'discarding the calendar' => [
        fn (object $test, Throwable $failure) => $test->provisioning->failDiscardingWith($failure),
    ],
    'looking for a live connection of the account' => [
        fn (object $test, Throwable $failure) => $test->connections->failAccountLookupWith($failure),
    ],
    'revoking the grant' => [
        fn (object $test, Throwable $failure) => $test->provisioning->failRevokingGrantWith($failure),
    ],
]);

describe('a compensation that fails itself', function () {
    beforeEach(function () {
        $this->compensationFailure = new RuntimeException('The calendar provider is unreachable.');
        $this->connections->failSavingWith(CalendarAlreadyConnected::forStaffMember(IntegrationsFixtures::STAFF_MEMBER_ID));
    });

    it('still answers with the original failure', function (Closure $arrange) {
        $arrange($this, $this->compensationFailure);

        $response = ($this->complete)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('calendar_already_connected')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause())->toBeInstanceOf(CalendarAlreadyConnected::class);
    })->with('compensations that fail');

    it('reports the compensation failure exactly once', function (Closure $arrange) {
        $arrange($this, $this->compensationFailure);

        ($this->complete)();

        expect($this->reported)->toHaveCount(1)
            ->and($this->reported[0])->toBe($this->compensationFailure);
    })->with('compensations that fail');

    it('announces nothing', function (Closure $arrange) {
        $arrange($this, $this->compensationFailure);

        ($this->complete)();

        expect($this->dispatched)->toBe([]);
    })->with('compensations that fail');

    it('still revokes the grant when discarding the calendar failed', function () {
        $this->provisioning->failDiscardingWith($this->compensationFailure);

        ($this->complete)();

        expect($this->provisioning->revokedGrants)->toEqual([IntegrationsFixtures::grant()]);
    });

    it('does not revoke the grant when it could not tell whether the account is still in use', function () {
        $this->connections->failAccountLookupWith($this->compensationFailure);

        ($this->complete)();

        expect($this->provisioning->revokedGrants)->toBe([]);
    });
});

describe('an unexpected failure after the exchange', function () {
    beforeEach(function () {
        $this->unexpected = new RuntimeException('The database connection was lost.');
        $this->connections->failSavingWith($this->unexpected);
    });

    it('rethrows the unexpected failure unchanged', function () {
        expect(fn () => ($this->complete)())
            ->toThrow(fn (RuntimeException $thrown) => expect($thrown)->toBe($this->unexpected));
    });

    it('discards the calendar and revokes the grant before rethrowing', function () {
        expect(fn () => ($this->complete)())->toThrow(RuntimeException::class);

        expect($this->provisioning->discarded)->toEqual([[
            'grant' => IntegrationsFixtures::grant(),
            'externalCalendarId' => IntegrationsFixtures::EXTERNAL_CALENDAR_ID,
        ]])->and($this->provisioning->revokedGrants)->toEqual([IntegrationsFixtures::grant()])
            ->and($this->dispatched)->toBe([]);
    });

    it('rethrows the original failure, not the one a compensation raised', function () {
        $compensationFailure = new LogicException('The calendar could not be deleted.');
        $this->provisioning->failDiscardingWith($compensationFailure);

        expect(fn () => ($this->complete)())
            ->toThrow(fn (RuntimeException $thrown) => expect($thrown)->toBe($this->unexpected));

        expect($this->reported)->toBe([$compensationFailure]);
    });
});
