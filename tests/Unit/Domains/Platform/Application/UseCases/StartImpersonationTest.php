<?php

declare(strict_types=1);

use App\Domains\Platform\Application\Dtos\ImpersonationData;
use App\Domains\Platform\Application\Dtos\StartImpersonationInput;
use App\Domains\Platform\Application\UseCases\StartImpersonation;
use App\Domains\Platform\Exceptions\BusinessHasNoOwner;
use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Platform\FakeBusinessOwnerAccounts;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\RecordingImpersonationAuditTrail;
use Tests\Support\Platform\RecordingImpersonationSession;

beforeEach(function () {
    $this->owners = (new FakeBusinessOwnerAccounts)->owning(ImpersonationFixtures::owner());
    $this->transactions = new FakeTransactionManager;
    $this->session = new RecordingImpersonationSession($this->transactions);
    $this->auditTrail = new RecordingImpersonationAuditTrail($this->transactions);
    $this->clock = new FakeClock(ImpersonationFixtures::now());

    $this->start = fn (
        string $businessId = ImpersonationFixtures::BUSINESS_ID,
        ?string $ipAddress = ImpersonationFixtures::IP_ADDRESS,
    ) => (new StartImpersonation(
        $this->owners,
        $this->session,
        $this->auditTrail,
        $this->transactions,
        new FixedIdGenerator(ImpersonationFixtures::IMPERSONATION_ID),
        $this->clock,
    ))->handle(new StartImpersonationInput(ImpersonationFixtures::ADMIN_ID, $businessId, $ipAddress));
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

describe('the audit row', function () {
    it('records exactly one start, for the impersonation the session holds', function () {
        ($this->start)();

        expect($this->auditTrail->started)->toHaveCount(1)
            ->and($this->auditTrail->started[0]['impersonation'])->toBe($this->session->started[0])
            ->and($this->auditTrail->ended)->toBe([]);
    });

    it('identifies the row by the uuid the id generator handed out', function () {
        ($this->start)();

        expect($this->auditTrail->started[0]['impersonation']->id)->toBe(ImpersonationFixtures::IMPERSONATION_ID)
            ->and($this->auditTrail->started[0]['impersonation']->id)->toMatch(ImpersonationFixtures::UUID_PATTERN);
    });

    it('records who impersonated whom, in which business, from when', function () {
        ($this->start)();

        $recorded = $this->auditTrail->started[0]['impersonation'];

        expect($recorded->adminId)->toBe(ImpersonationFixtures::ADMIN_ID)
            ->and($recorded->accountId)->toBe(ImpersonationFixtures::ACCOUNT_ID)
            ->and($recorded->businessId)->toBe(ImpersonationFixtures::BUSINESS_ID)
            ->and($recorded->startedAt)->toEqual(ImpersonationFixtures::now());
    });

    it('records the address the admin started it from', function (?string $ipAddress) {
        ($this->start)(ipAddress: $ipAddress);

        expect($this->auditTrail->started[0]['ipAddress'])->toBe($ipAddress);
    })->with([
        'an ipv4 address' => [ImpersonationFixtures::IP_ADDRESS],
        'an ipv6 address' => ['2001:db8:85a3::8a2e:370:7334'],
        'no address at all' => [null],
    ]);

    it('writes the row and starts the session inside one transaction', function () {
        ($this->start)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->auditTrail->started[0]['insideTransaction'])->toBeTrue()
            ->and($this->session->startedInsideTransaction)->toBe([true]);
    });

    it('does not start the session when the row cannot be written', function () {
        $this->auditTrail->failingToRecordStartWith(new RuntimeException('connection lost'));

        expect(fn () => ($this->start)())->toThrow(RuntimeException::class, 'connection lost')
            ->and($this->session->started)->toBe([]);
    });
});

describe('refusing', function () {
    it('refuses a business it cannot find as not found, without touching the session or the audit trail', function () {
        $response = ($this->start)(ImpersonationFixtures::OTHER_BUSINESS_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('impersonated_business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0)
            ->and($this->auditTrail->started)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('refuses a business id that is no uuid as not found, without asking for an owner or writing anything', function (string $businessId) {
        $response = ($this->start)($businessId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('impersonated_business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->owners->businessIdsAsked)->toBe([])
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0)
            ->and($this->auditTrail->started)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    })->with([
        'empty' => [''],
        'an int primary key' => ['42'],
        'a slug' => ['barberia-centro'],
    ]);

    it('refuses a business with no owner as a conflict, without touching the session or the audit trail', function () {
        $this->owners->refusing(ImpersonationFixtures::BUSINESS_ID, BusinessHasNoOwner::forBusiness(ImpersonationFixtures::BUSINESS_ID));

        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_has_no_owner')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0)
            ->and($this->auditTrail->started)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('refuses a business whose owner was deactivated as a conflict, without touching the session or the audit trail', function () {
        $this->owners->refusing(ImpersonationFixtures::BUSINESS_ID, BusinessOwnerDeactivated::forBusiness(ImpersonationFixtures::BUSINESS_ID));

        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_owner_deactivated')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->session->started)->toBe([])
            ->and($this->session->endings)->toBe(0)
            ->and($this->auditTrail->started)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('lets a failure that is no refusal escape to the caller', function () {
        $this->owners->refusing(ImpersonationFixtures::BUSINESS_ID, new RuntimeException('connection lost'));

        expect(fn () => ($this->start)())->toThrow(RuntimeException::class, 'connection lost');
    });
});

describe('a session that refuses to start after the owner was found', function () {
    beforeEach(function () {
        $this->session->refusingToStartWith(BusinessOwnerDeactivated::forBusiness(ImpersonationFixtures::BUSINESS_ID));
        $this->response = ($this->start)();
    });

    it('returns the deactivation the session reports', function () {
        expect($this->response->failed())->toBeTrue()
            ->and($this->response->error()->code)->toBe('business_owner_deactivated')
            ->and($this->response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('wrote its audit row inside the transaction the refusal aborts, so the row rolls back', function () {
        expect($this->auditTrail->started)->toHaveCount(1)
            ->and($this->auditTrail->started[0]['insideTransaction'])->toBeTrue()
            ->and($this->transactions->runs())->toBe(1)
            ->and($this->transactions->isRunning())->toBeFalse();
    });

    it('leaves no impersonation behind', function () {
        expect($this->session->started)->toBe([])
            ->and($this->session->current())->toBeNull()
            ->and($this->auditTrail->ended)->toBe([]);
    });
});
