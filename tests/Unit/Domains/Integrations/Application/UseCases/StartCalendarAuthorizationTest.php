<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\AuthorizationUrlData;
use App\Domains\Integrations\Application\Dtos\StartCalendarAuthorizationInput;
use App\Domains\Integrations\Application\UseCases\StartCalendarAuthorization;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarAuthorizationStates;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarAuthorizer;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarConnectionRepository;
use Tests\Unit\Domains\Integrations\Application\Doubles\FakeCalendarOwners;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsJournal;

beforeEach(function () {
    $this->journal = new IntegrationsJournal;
    $this->owners = (new FakeCalendarOwners($this->journal))
        ->member(IntegrationsFixtures::BUSINESS_ID, IntegrationsFixtures::ACCOUNT_ID, IntegrationsFixtures::STAFF_MEMBER_ID);
    $this->connections = new FakeCalendarConnectionRepository($this->journal);
    $this->states = new FakeCalendarAuthorizationStates($this->journal);
    $this->authorizer = new FakeCalendarAuthorizer($this->journal);

    $this->start = fn (string $accountId = IntegrationsFixtures::ACCOUNT_ID) => (new StartCalendarAuthorization(
        $this->owners,
        $this->connections,
        $this->states,
        $this->authorizer,
        new FakeBusinessContext,
    ))->handle(new StartCalendarAuthorizationInput($accountId));
});

describe('a staff member with no calendar connected', function () {
    it('answers with the provider authorization url carrying the issued state', function () {
        $data = ($this->start)()->value();

        expect($data)->toBeInstanceOf(AuthorizationUrlData::class)
            ->and($data->authorizationUrl)->toBe(FakeCalendarAuthorizer::AUTHORIZATION_ENDPOINT.IntegrationsFixtures::STATE);
    });

    it('issues exactly one state bound to the caller account, business and staff member', function () {
        ($this->start)();

        expect($this->states->issued)->toHaveCount(1)
            ->and($this->states->issued[0]->accountId)->toBe(IntegrationsFixtures::ACCOUNT_ID)
            ->and($this->states->issued[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->states->issued[0]->staffMemberId)->toBe(IntegrationsFixtures::STAFF_MEMBER_ID);
    });

    it('resolves the staff member in the business in context and checks for a google connection there', function () {
        ($this->start)();

        expect($this->owners->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => IntegrationsFixtures::ACCOUNT_ID,
        ]])->and($this->connections->staffMemberLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffMemberId' => IntegrationsFixtures::STAFF_MEMBER_ID,
            'provider' => CalendarProvider::Google,
        ]]);
    });

    it('is not blocked by a colleague who already connected a calendar', function () {
        $this->connections->store(IntegrationsFixtures::connection(
            staffMemberId: IntegrationsFixtures::SECOND_STAFF_MEMBER_ID,
        ));

        expect(($this->start)()->succeeded())->toBeTrue();
    });
});

describe('a staff member whose connection needs reconnecting', function () {
    it('lets the staff member authorize again', function () {
        $this->connections->store(IntegrationsFixtures::awaitingReconnect());

        $response = ($this->start)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->authorizationUrl)->toContain(IntegrationsFixtures::STATE)
            ->and($this->states->issued)->toHaveCount(1);
    });
});

describe('a staff member whose calendar is already connected', function () {
    beforeEach(function () {
        $this->connections->store(IntegrationsFixtures::connection());
    });

    it('refuses with calendar already connected', function () {
        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('calendar_already_connected')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('issues no state and builds no url', function () {
        ($this->start)();

        expect($this->states->issued)->toBe([])
            ->and($this->journal->entries)->not->toContain('authorizer.authorizationUrl');
    });
});

describe('a caller who is no staff member of the business', function () {
    it('refuses with staff member not found', function () {
        $response = ($this->start)(IntegrationsFixtures::OTHER_ACCOUNT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('touches nothing past the membership lookup', function () {
        ($this->start)(IntegrationsFixtures::OTHER_ACCOUNT_ID);

        expect($this->journal->entries)->toBe(['owners.staffMemberIdOf']);
    });
});
