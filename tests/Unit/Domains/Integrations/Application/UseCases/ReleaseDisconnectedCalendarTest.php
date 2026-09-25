<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\ReleaseDisconnectedCalendarInput;
use App\Domains\Integrations\Application\UseCases\ReleaseDisconnectedCalendar;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarProvisioning;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsJournal;

beforeEach(function () {
    $this->journal = new IntegrationsJournal;
    $this->connections = new FakeCalendarConnectionRepository($this->journal);
    $this->provisioning = new FakeCalendarProvisioning($this->journal);

    $this->release = fn (
        string $businessId = IntegrationsFixtures::BUSINESS_ID,
        string $connectionId = IntegrationsFixtures::CONNECTION_ID,
    ) => (new ReleaseDisconnectedCalendar($this->connections, $this->provisioning))
        ->handle(new ReleaseDisconnectedCalendarInput($businessId, $connectionId));
});

describe('a disconnected calendar whose account holds no other connection', function () {
    beforeEach(function () {
        $this->connections->storeDisconnected(IntegrationsFixtures::connection());
    });

    it('answers with an empty success', function () {
        $response = ($this->release)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('deletes the dedicated calendar of the connection', function () {
        ($this->release)();

        expect($this->provisioning->deletedCalendars)->toBe([IntegrationsFixtures::CONNECTION_ID]);
    });

    it('revokes the authorization of the account', function () {
        ($this->release)();

        expect($this->provisioning->revokedAuthorizations)->toBe([IntegrationsFixtures::CONNECTION_ID]);
    });

    it('deletes the calendar before it revokes the authorization it needs to do so', function () {
        ($this->release)();

        expect(array_search('provisioning.deleteCalendar', $this->journal->entries, true))
            ->toBeLessThan(array_search('provisioning.revokeAuthorization', $this->journal->entries, true));
    });

    it('asks whether the account is still live by provider and email', function () {
        ($this->release)();

        expect($this->connections->accountLookups)->toBe([[
            'provider' => CalendarProvider::Google,
            'accountEmail' => IntegrationsFixtures::ACCOUNT_EMAIL,
        ]]);
    });
});

describe('a disconnected calendar whose account still backs another connection', function () {
    beforeEach(function () {
        $this->connections->storeDisconnected(IntegrationsFixtures::connection());
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            businessId: IntegrationsFixtures::OTHER_BUSINESS_ID,
        ));
    });

    it('still deletes the dedicated calendar of the disconnected connection', function () {
        ($this->release)();

        expect($this->provisioning->deletedCalendars)->toBe([IntegrationsFixtures::CONNECTION_ID]);
    });

    it('keeps the authorization the other connection depends on', function () {
        ($this->release)();

        expect($this->provisioning->revokedAuthorizations)->toBe([]);
    });

    it('keeps the authorization when the live connection only needs reconnecting', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            id: IntegrationsFixtures::SECOND_CONNECTION_ID,
            businessId: IntegrationsFixtures::OTHER_BUSINESS_ID,
            status: ConnectionStatus::NeedsReconnect,
        ));

        ($this->release)();

        expect($this->provisioning->revokedAuthorizations)->toBe([]);
    });
});

describe('nothing to release', function () {
    it('answers success and touches no calendar', function (Closure $arrange) {
        $arrange($this);

        $response = ($this->release)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->provisioning->deletedCalendars)->toBe([])
            ->and($this->provisioning->revokedAuthorizations)->toBe([]);
    })->with([
        'unknown connection' => [fn (object $test) => null],
        'connection still live' => [fn (object $test) => $test->connections->store(IntegrationsFixtures::connection())],
        'disconnected connection of another business' => [fn (object $test) => $test->connections->storeDisconnected(
            IntegrationsFixtures::connection(businessId: IntegrationsFixtures::OTHER_BUSINESS_ID),
        )],
    ]);
});
