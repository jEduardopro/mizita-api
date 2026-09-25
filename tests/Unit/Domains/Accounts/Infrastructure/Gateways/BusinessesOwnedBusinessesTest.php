<?php

declare(strict_types=1);

use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Domains\Accounts\Infrastructure\Gateways\BusinessesOwnedBusinesses;
use App\Domains\Accounts\ValueObjects\ClosedBusinessSnapshot;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;
use App\Domains\Businesses\Application\UseCases\CloseBusiness;
use App\Domains\Businesses\Application\UseCases\ReopenBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PaymentMethodProvisioner;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Contracts\ScheduleProvisioner;
use App\Domains\Businesses\Contracts\TeamSignOut;
use App\Domains\Businesses\Exceptions\BusinessAlreadyClosed;
use App\Domains\Businesses\Exceptions\BusinessNotClosed;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Businesses\ClosureFixtures;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;

const OWNED_BUSINESSES_OTHER_BUSINESS_ID = '01930000-0000-7000-8000-000000000002';

beforeEach(function () {
    $this->businesses = new FakeBusinessRepository;
    $this->teamSignOut = Mockery::mock(TeamSignOut::class);
    $this->roles = Mockery::mock(RoleProvisioner::class);
    $this->paymentMethods = Mockery::mock(PaymentMethodProvisioner::class);
    $this->owners = Mockery::mock(OwnerRegistrar::class);
    $this->schedules = Mockery::mock(ScheduleProvisioner::class);
    $this->events = Mockery::mock(Dispatcher::class);

    $this->buildGateway = fn (BusinessRepository $businesses): BusinessesOwnedBusinesses => new BusinessesOwnedBusinesses(
        $businesses,
        new CloseBusiness($businesses, $this->teamSignOut, new FakeClock(ClosureFixtures::closedAt()), new FakeTransactionManager),
        new ReopenBusiness(
            $businesses,
            $this->roles,
            $this->paymentMethods,
            $this->owners,
            $this->schedules,
            new FakeTransactionManager,
            $this->events,
        ),
    );

    $this->gateway = ($this->buildGateway)($this->businesses);
});

it('implements the port the Accounts domain declared', function () {
    expect($this->gateway)->toBeInstanceOf(OwnedBusinesses::class);
});

describe('describing an owned business', function () {
    it('answers with the uuid and the name of the business', function () {
        $this->businesses->store(OnboardingFixtures::business());

        $snapshot = $this->gateway->describe(OnboardingFixtures::GENERATED_BUSINESS_ID);

        expect($snapshot)->toBeInstanceOf(OwnedBusinessSnapshot::class)
            ->and($snapshot->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($snapshot->name)->toBe(OnboardingFixtures::NAME)
            ->and($this->businesses->idsRead)->toBe([OnboardingFixtures::GENERATED_BUSINESS_ID]);
    });

    it('throws not found for a business the repository does not have', function () {
        expect(fn () => $this->gateway->describe(OWNED_BUSINESSES_OTHER_BUSINESS_ID))
            ->toThrow(BusinessNotFound::class);
    });
});

describe('closing an owned business', function () {
    it('closes it through the use case, signing the team out and saving the closure', function () {
        $this->businesses->store(OnboardingFixtures::business());
        $this->teamSignOut->shouldReceive('signOutTeamOf')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);

        $this->gateway->close(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($this->businesses->saved)->toHaveCount(1)
            ->and($this->businesses->saved[0]->isClosed())->toBeTrue()
            ->and($this->businesses->saved[0]->closedAt())->toEqual(ClosureFixtures::closedAt())
            ->and($this->businesses->saved[0]->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID);
    });

    it('throws the refusal the use case answered with, because a port protocol is to throw', function () {
        $this->teamSignOut->shouldNotReceive('signOutTeamOf');

        expect(fn () => $this->gateway->close(OWNED_BUSINESSES_OTHER_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID))
            ->toThrow(BusinessNotFound::class, 'Business ['.OWNED_BUSINESSES_OTHER_BUSINESS_ID.'] was not found.');

        expect($this->businesses->saved)->toBe([]);
    });

    it('throws already closed when the repository hands back a business that is closed', function () {
        $businesses = Mockery::mock(BusinessRepository::class);
        $businesses->shouldReceive('findById')->andReturn(ClosureFixtures::closedBusiness());
        $businesses->shouldNotReceive('save');
        $this->teamSignOut->shouldNotReceive('signOutTeamOf');

        expect(fn () => ($this->buildGateway)($businesses)->close(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID))
            ->toThrow(BusinessAlreadyClosed::class);
    });
});

describe('finding the business an account closed', function () {
    it('answers with nothing when the account closed no business', function () {
        $this->businesses->store(OnboardingFixtures::business());

        expect($this->gateway->closedBusinessOf(OnboardingFixtures::OWNER_ACCOUNT_ID))->toBeNull()
            ->and($this->businesses->closedOwnersRead)->toBe([OnboardingFixtures::OWNER_ACCOUNT_ID]);
    });

    it('describes the closure with the purge scheduled thirty days after it', function () {
        $this->businesses->store(ClosureFixtures::closedBusiness());

        $snapshot = $this->gateway->closedBusinessOf(OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($snapshot)->toBeInstanceOf(ClosedBusinessSnapshot::class)
            ->and($snapshot->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($snapshot->name)->toBe(OnboardingFixtures::NAME)
            ->and($snapshot->closedAt)->toEqual(ClosureFixtures::closedAt())
            ->and($snapshot->purgeScheduledAt)->toEqual(ClosureFixtures::purgeDueAt())
            ->and($snapshot->purged)->toBeFalse();
    });

    it('flags a business the purge has already emptied', function () {
        $this->businesses->store(ClosureFixtures::purgedBusiness());

        $snapshot = $this->gateway->closedBusinessOf(OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($snapshot->purged)->toBeTrue()
            ->and($snapshot->purgeScheduledAt)->toEqual(ClosureFixtures::purgeDueAt());
    });

    it('ignores a business another account closed', function () {
        $this->businesses->store(ClosureFixtures::closedBusiness(closedBy: ClosureFixtures::STRANGER_ACCOUNT_ID));

        expect($this->gateway->closedBusinessOf(OnboardingFixtures::OWNER_ACCOUNT_ID))->toBeNull();
    });

    it('refuses to describe as closed a business the repository returned open', function () {
        $businesses = Mockery::mock(BusinessRepository::class);
        $businesses->shouldReceive('findClosedOwnedBy')->once()
            ->with(OnboardingFixtures::OWNER_ACCOUNT_ID)->andReturn(OnboardingFixtures::business());

        expect(fn () => ($this->buildGateway)($businesses)->closedBusinessOf(OnboardingFixtures::OWNER_ACCOUNT_ID))
            ->toThrow(LogicException::class, 'was returned as closed without a closing instant');
    });
});

describe('reopening an owned business', function () {
    it('reopens it through the use case with its data intact during the grace period', function () {
        $this->businesses->store(ClosureFixtures::closedBusiness());
        $this->roles->shouldNotReceive('provisionFor');
        $this->owners->shouldNotReceive('registerOwner');
        $this->events->shouldNotReceive('dispatch');

        $this->gateway->reopen(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($this->businesses->closedIdsRead)->toBe([OnboardingFixtures::GENERATED_BUSINESS_ID])
            ->and($this->businesses->saved)->toHaveCount(1)
            ->and($this->businesses->saved[0]->isClosed())->toBeFalse()
            ->and($this->gateway->closedBusinessOf(OnboardingFixtures::OWNER_ACCOUNT_ID))->toBeNull();
    });

    it('reprovisions a purged business for the owner who reopens it', function () {
        $this->businesses->store(ClosureFixtures::purgedBusiness());
        $this->roles->shouldReceive('provisionFor')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->paymentMethods->shouldReceive('provisionFor')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->andReturn(OnboardingFixtures::ownerRegistration());
        $this->schedules->shouldReceive('provisionDefaultsFor')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_STAFF_MEMBER_ID);

        $this->gateway->reopen(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID);

        expect($this->businesses->saved[0]->isPurged())->toBeFalse();
    });

    it('throws not closed for a business that is open', function () {
        $this->businesses->store(OnboardingFixtures::business());

        expect(fn () => $this->gateway->reopen(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID))
            ->toThrow(BusinessNotClosed::class);

        expect($this->businesses->saved)->toBe([]);
    });

    it('throws not closed for an account other than the one that closed it', function () {
        $this->businesses->store(ClosureFixtures::closedBusiness());

        expect(fn () => $this->gateway->reopen(OnboardingFixtures::GENERATED_BUSINESS_ID, ClosureFixtures::STRANGER_ACCOUNT_ID))
            ->toThrow(BusinessNotClosed::class, 'was not closed by account ['.ClosureFixtures::STRANGER_ACCOUNT_ID.']');

        expect($this->businesses->saved)->toBe([]);
    });
});
