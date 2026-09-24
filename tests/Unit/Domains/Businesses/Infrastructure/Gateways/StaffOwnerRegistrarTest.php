<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\Infrastructure\Gateways\StaffOwnerRegistrar;
use App\Domains\Businesses\ValueObjects\OwnerRegistration;
use App\Domains\Staff\Application\UseCases\RegisterBusinessOwner;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Events\StaffMemberRegistered;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseResponse;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Staff\FakeStaffProfileRepository;

beforeEach(function () {
    $this->registeredMemberId = '01930000-0000-7000-8000-0000000000c9';
    $this->registeredProfileId = '01930000-0000-7000-8000-0000000000e9';
    $this->staffMembers = Mockery::mock(StaffMemberRepository::class);
    $this->profiles = new FakeStaffProfileRepository;

    $this->registrar = new StaffOwnerRegistrar(new RegisterBusinessOwner(
        $this->staffMembers,
        $this->profiles,
        new FixedIdGenerator($this->registeredMemberId, $this->registeredProfileId),
        new FakeClock(OnboardingFixtures::now()),
        new FakeTransactionManager,
    ));

    $this->register = fn (): OwnerRegistration => $this->registrar->registerOwner(
        OnboardingFixtures::GENERATED_BUSINESS_ID,
        OnboardingFixtures::OWNER_ACCOUNT_ID,
    );
});

describe('registering the owner of a brand new business', function () {
    it('registers the owner membership of the new business for the given account', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()
            ->with(OnboardingFixtures::OWNER_ACCOUNT_ID)->andReturn(false);

        $saved = null;
        $this->staffMembers->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->register)();

        expect($saved)->toBeInstanceOf(StaffMember::class)
            ->and($saved->id)->toBe($this->registeredMemberId)
            ->and($saved->businessId)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($saved->accountId)->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->and($saved->role())->toBe(StaffRole::Owner);
    });

    it('hands back the uuid of the staff member it registered', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

        expect(($this->register)()->staffMemberId)->toBe($this->registeredMemberId);
    });

    it('hands back the staff member uuid, never the uuid of the profile created alongside it', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

        $staffMemberId = ($this->register)()->staffMemberId;

        expect($staffMemberId)->not->toBe($this->registeredProfileId)
            ->and($this->profiles->saved[0]->staffMemberId)->toBe($staffMemberId)
            ->and($this->profiles->saved[0]->businessId)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID);
    });

    it('hands back the events the staff use case raised', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

        $events = ($this->register)()->events;

        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(StaffMemberRegistered::class)
            ->and($events[0]->id)->toBe($this->registeredMemberId);
    });

    it('unwraps the response, so no use case response crosses the port', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

        expect(($this->register)())->toBeInstanceOf(OwnerRegistration::class)
            ->not->toBeInstanceOf(UseCaseResponse::class);
    });

    it('declares an owner registration on the port, which is what forbids answering with a failure', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(OwnerRegistrar::class))->getMethods(),
        );

        expect($returnTypes)->toBe([OwnerRegistration::class])
            ->and($returnTypes)->not->toContain(UseCaseResponse::class);
    });
});

describe('the rollback contract when the account already owns a business', function () {
    it('throws the businesses refusal rather than returning the staff failure response', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()->andReturn(true);
        $this->staffMembers->shouldNotReceive('save');

        try {
            ($this->register)();
            $thrown = null;
        } catch (Throwable $refusal) {
            $thrown = $refusal;
        }

        expect($thrown)->toBeInstanceOf(OwnerAlreadyHasBusiness::class)
            ->and($thrown->errorCode())->toBe('owner_already_has_business')
            ->and($thrown->getMessage())
            ->toBe('Account ['.OnboardingFixtures::OWNER_ACCOUNT_ID.'] already owns a business.');
    });

    it('keeps the staff refusal as the cause, which only works because value rethrows the original', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()->andReturn(true);

        try {
            ($this->register)();
            $thrown = null;
        } catch (Throwable $refusal) {
            $thrown = $refusal;
        }

        expect($thrown->getPrevious())->toBeInstanceOf(AccountAlreadyOwnsBusiness::class)
            ->and($thrown->getPrevious()->getMessage())
            ->toBe('Account ['.OnboardingFixtures::OWNER_ACCOUNT_ID.'] already owns a business.');
    });

    it('hands back the very exception the use case raised, not a rebuilt one', function () {
        $conflict = AccountAlreadyOwnsBusiness::forAccount(OnboardingFixtures::OWNER_ACCOUNT_ID);

        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once()->andThrow($conflict);

        try {
            ($this->register)();
            $thrown = null;
        } catch (Throwable $refusal) {
            $thrown = $refusal;
        }

        expect($thrown)->toBeInstanceOf(OwnerAlreadyHasBusiness::class)
            ->and($thrown->getPrevious())->toBe($conflict);
    });

    it('lets an infrastructure error out untranslated', function () {
        $bug = new RuntimeException('the staff members table is gone');

        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()->andThrow($bug);
        $this->staffMembers->shouldNotReceive('save');

        try {
            ($this->register)();
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($bug);
    });
});
