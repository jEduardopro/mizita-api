<?php

declare(strict_types=1);

use App\Domains\Platform\Application\Dtos\ImpersonationData;
use App\Domains\Platform\Application\Dtos\StartImpersonationInput;
use App\Domains\Platform\Application\UseCases\StartImpersonation;
use App\Domains\Platform\Exceptions\BusinessHasNoOwner;
use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\Platform\FakeBusinessOwnerAccounts;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\RecordingImpersonationSession;

beforeEach(function () {
    $this->owners = (new FakeBusinessOwnerAccounts)->owning(ImpersonationFixtures::owner());
    $this->session = new RecordingImpersonationSession;
    $this->clock = new FakeClock(ImpersonationFixtures::now());

    $this->start = fn (string $businessId = ImpersonationFixtures::BUSINESS_ID) => (new StartImpersonation($this->owners, $this->session, $this->clock))
        ->handle(new StartImpersonationInput(ImpersonationFixtures::ADMIN_ID, $businessId));
});

describe('starting', function () {
    it('answers with the business, the owner and the instant the impersonation expires', function () {
        $data = ($this->start)()->value();

        expect($data)->toBeInstanceOf(ImpersonationData::class)
            ->and($data->businessId)->toBe(ImpersonationFixtures::BUSINESS_ID)
            ->and($data->businessName)->toBe(ImpersonationFixtures::BUSINESS_NAME)
            ->and($data->ownerName)->toBe(ImpersonationFixtures::OWNER_NAME)
            ->and($data->expiresAt)->toEqual(new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT));
    });

    it('hands back the business under its uuid', function () {
        expect(($this->start)()->value()->businessId)->toMatch(ImpersonationFixtures::UUID_PATTERN);
    });

    it('starts the session exactly once, for the admin, the owner account and the business', function () {
        ($this->start)();

        expect($this->session->started)->toHaveCount(1)
            ->and($this->session->started[0]->adminId)->toBe(ImpersonationFixtures::ADMIN_ID)
            ->and($this->session->started[0]->accountId)->toBe(ImpersonationFixtures::ACCOUNT_ID)
            ->and($this->session->started[0]->businessId)->toBe(ImpersonationFixtures::BUSINESS_ID)
            ->and($this->session->started[0]->businessName)->toBe(ImpersonationFixtures::BUSINESS_NAME)
            ->and($this->session->started[0]->ownerName)->toBe(ImpersonationFixtures::OWNER_NAME);
    });

    it('starts the impersonation at the instant the clock reads and expires it an hour later', function () {
        ($this->start)();

        expect($this->session->started[0]->startedAt)->toEqual(ImpersonationFixtures::now())
            ->and($this->session->started[0]->expiresAt)->toEqual(new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT));
    });

    it('takes the expiry from the clock, never from a fixed instant', function () {
        $this->clock->advance('PT2H30M');

        expect(($this->start)()->value()->expiresAt)->toEqual(new DateTimeImmutable('2026-09-25T18:30:00+00:00'));
    });

    it('asks for the owner of exactly the business requested', function () {
        ($this->start)();

        expect($this->owners->businessIdsAsked)->toBe([ImpersonationFixtures::BUSINESS_ID]);
    });

    it('never ends a session while starting one', function () {
        ($this->start)();

        expect($this->session->endings)->toBe(0);
    });
});

describe('refusing', function () {
    it('refuses a business it cannot find as not found, without touching the session', function () {
        $response = ($this->start)('01930000-0000-7000-8000-0000000ab999');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('impersonated_business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0);
    });

    it('refuses a business id that is no uuid as not found, without asking for an owner or touching the session', function (string $businessId) {
        $response = ($this->start)($businessId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('impersonated_business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->owners->businessIdsAsked)->toBe([])
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0);
    })->with([
        'empty' => [''],
        'an int primary key' => ['42'],
        'a slug' => ['barberia-centro'],
    ]);

    it('refuses a business with no owner as a conflict, without touching the session', function () {
        $this->owners->refusing(ImpersonationFixtures::BUSINESS_ID, BusinessHasNoOwner::forBusiness(ImpersonationFixtures::BUSINESS_ID));

        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_has_no_owner')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0);
    });

    it('refuses a business whose owner was deactivated as a conflict, without touching the session', function () {
        $this->owners->refusing(ImpersonationFixtures::BUSINESS_ID, BusinessOwnerDeactivated::forBusiness(ImpersonationFixtures::BUSINESS_ID));

        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_owner_deactivated')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0);
    });

    it('returns the deactivation the session reports when the owner vanished between lookup and sign in', function () {
        $this->session->refusingToStartWith(BusinessOwnerDeactivated::forBusiness(ImpersonationFixtures::BUSINESS_ID));

        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_owner_deactivated')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('lets a failure that is no refusal escape to the caller', function () {
        $this->owners->refusing(ImpersonationFixtures::BUSINESS_ID, new RuntimeException('connection lost'));

        expect(fn () => ($this->start)())->toThrow(RuntimeException::class, 'connection lost');
    });
});
