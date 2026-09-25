<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\CalendarConnectionData;
use App\Domains\Integrations\Application\Dtos\IntegrationData;
use App\Domains\Integrations\Application\Dtos\ListIntegrationsInput;
use App\Domains\Integrations\Application\UseCases\ListIntegrations;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Integrations\ValueObjects\IntegrationCategory;
use App\Domains\Integrations\ValueObjects\IntegrationKey;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarOwners;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsJournal;

beforeEach(function () {
    $this->journal = new IntegrationsJournal;
    $this->owners = (new FakeCalendarOwners($this->journal))
        ->member(IntegrationsFixtures::BUSINESS_ID, IntegrationsFixtures::ACCOUNT_ID, IntegrationsFixtures::STAFF_MEMBER_ID);
    $this->connections = new FakeCalendarConnectionRepository($this->journal);

    $this->list = fn (string $accountId = IntegrationsFixtures::ACCOUNT_ID) => (new ListIntegrations(
        $this->owners,
        $this->connections,
        new FakeBusinessContext,
    ))->handle(new ListIntegrationsInput($accountId));
});

describe('the catalogue of integrations', function () {
    it('lists every integration the product offers, one entry per key', function () {
        $integrations = ($this->list)()->value();

        expect($integrations)->toHaveCount(count(IntegrationKey::cases()))
            ->and($integrations[0])->toBeInstanceOf(IntegrationData::class);
    });

    it('describes google calendar as a calendar sync integration', function () {
        $integration = ($this->list)()->value()[0];

        expect($integration->key)->toBe(IntegrationKey::GoogleCalendar)
            ->and($integration->category)->toBe(IntegrationCategory::CalendarSync);
    });

    it('reports no connection when the caller never connected a calendar', function () {
        expect(($this->list)()->value()[0]->connection)->toBeNull();
    });
});

describe('the connection of the caller', function () {
    it('hands back the caller connection field by field', function () {
        $this->connections->store(IntegrationsFixtures::connection());

        $connection = ($this->list)()->value()[0]->connection;

        expect($connection)->toBeInstanceOf(CalendarConnectionData::class)
            ->and($connection->id)->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($connection->status)->toBe(ConnectionStatus::Connected)
            ->and($connection->accountEmail)->toBe(IntegrationsFixtures::ACCOUNT_EMAIL)
            ->and($connection->connectedAt)->toEqual(IntegrationsFixtures::instant(IntegrationsFixtures::CONNECTED_AT));
    });

    it('hands back the connection uuid, never an internal key', function () {
        $this->connections->store(IntegrationsFixtures::connection());

        $id = ($this->list)()->value()[0]->connection->id;

        expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
            ->and(is_numeric($id))->toBeFalse();
    });

    it('surfaces a connection that needs reconnecting so the caller can repair it', function () {
        $this->connections->store(IntegrationsFixtures::awaitingReconnect());

        expect(($this->list)()->value()[0]->connection->status)->toBe(ConnectionStatus::NeedsReconnect);
    });

    it('looks the connection up for the caller staff member, in the business in context, with google', function () {
        ($this->list)();

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

describe('connections that are not the caller own', function () {
    it('does not report the connection of another team member of the same business', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));

        expect(($this->list)()->value()[0]->connection)->toBeNull();
    });

    it('does not report a connection the same staff identity holds in another business', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            businessId: IntegrationsFixtures::OTHER_BUSINESS_ID,
        ));

        expect(($this->list)()->value()[0]->connection)->toBeNull();
    });

    it('reports only the caller connection when a colleague is connected too', function () {
        $this->connections->store(
            IntegrationsFixtures::connection(
                id: IntegrationsFixtures::SECOND_CONNECTION_ID,
                staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
            ),
            IntegrationsFixtures::connection(),
        );

        expect(($this->list)()->value()[0]->connection->id)->toBe(IntegrationsFixtures::CONNECTION_ID);
    });
});

describe('a caller who is no staff member of the business', function () {
    it('refuses with staff member not found', function () {
        $response = ($this->list)(IntegrationsFixtures::OTHER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('reads no connection before refusing', function () {
        ($this->list)(IntegrationsFixtures::OTHER_ACCOUNT_ID);

        expect($this->journal->entries)->toBe(['owners.staffMemberIdOf']);
    });
});
