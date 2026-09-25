<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\ReopenBusinessInput;
use App\Domains\Businesses\Application\UseCases\ReopenBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PaymentMethodProvisioner;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Contracts\ScheduleProvisioner;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Businesses\ClosureFixtures;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeTransactionManager;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->roles = Mockery::mock(RoleProvisioner::class);
    $this->paymentMethods = Mockery::mock(PaymentMethodProvisioner::class);
    $this->owners = Mockery::mock(OwnerRegistrar::class);
    $this->schedules = Mockery::mock(ScheduleProvisioner::class);
    $this->events = Mockery::mock(Dispatcher::class);
    $this->transactions = new FakeTransactionManager;

    $this->useCase = new ReopenBusiness(
        $this->businesses,
        $this->roles,
        $this->paymentMethods,
        $this->owners,
        $this->schedules,
        $this->transactions,
        $this->events,
    );

    $this->input = new ReopenBusinessInput(
        businessId: OnboardingFixtures::GENERATED_BUSINESS_ID,
        ownerAccountId: OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    $this->ownerEvents = [new stdClass, new stdClass];

    $this->insideTransaction = [];
    $this->recordTransactionState = function (): bool {
        $this->insideTransaction[] = $this->transactions->isRunning();

        return true;
    };

    $this->expectNoReprovisioning = function (): void {
        $this->roles->shouldNotReceive('provisionFor');
        $this->paymentMethods->shouldNotReceive('provisionFor');
        $this->owners->shouldNotReceive('registerOwner');
        $this->schedules->shouldNotReceive('provisionDefaultsFor');
    };
});

describe('reopening within the grace period', function () {
    beforeEach(function () {
        $this->closed = ClosureFixtures::closedBusiness();
        $this->businesses->shouldReceive('findClosedById')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID)->andReturn($this->closed);
    });

    it('saves the business open again and answers with success', function () {
        $saved = null;
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));
        ($this->expectNoReprovisioning)();
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle($this->input);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull()
            ->and($saved)->toBe($this->closed)
            ->and($saved->isClosed())->toBeFalse()
            ->and($saved->closedByAccountId())->toBeNull()
            ->and($saved->purgeScheduledAt())->toBeNull();
    });

    it('provisions nothing, because the data never left', function () {
        $this->businesses->shouldReceive('save')->once();
        ($this->expectNoReprovisioning)();
        $this->events->shouldNotReceive('dispatch');

        $this->useCase->handle($this->input);
    });

    it('saves inside a transaction', function () {
        $this->businesses->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->events->shouldNotReceive('dispatch');

        $this->useCase->handle($this->input);

        expect($this->insideTransaction)->toBe([true])
            ->and($this->transactions->runs())->toBe(1);
    });
});

describe('reopening after the purge', function () {
    beforeEach(function () {
        $this->purged = ClosureFixtures::purgedBusiness();
        $this->businesses->shouldReceive('findClosedById')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID)->andReturn($this->purged);
    });

    it('saves the business open and unpurged', function () {
        $saved = null;
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));
        $this->roles->shouldReceive('provisionFor')->once();
        $this->paymentMethods->shouldReceive('provisionFor')->once();
        $this->owners->shouldReceive('registerOwner')->once()->andReturn(OnboardingFixtures::ownerRegistration());
        $this->schedules->shouldReceive('provisionDefaultsFor')->once();

        expect($this->useCase->handle($this->input)->succeeded())->toBeTrue()
            ->and($saved)->toBe($this->purged)
            ->and($saved->isClosed())->toBeFalse()
            ->and($saved->isPurged())->toBeFalse()
            ->and($saved->purgedAt())->toBeNull();
    });

    it('reprovisions the business exactly as onboarding does, for that business and that owner', function () {
        $registeredMemberId = '01930000-0000-7000-8000-0000000000b7';

        $this->businesses->shouldReceive('save')->once();
        $this->roles->shouldReceive('provisionFor')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->paymentMethods->shouldReceive('provisionFor')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->andReturn(OnboardingFixtures::ownerRegistration(staffMemberId: $registeredMemberId));
        $this->schedules->shouldReceive('provisionDefaultsFor')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, $registeredMemberId);

        $this->useCase->handle($this->input);
    });

    it('saves first, then roles, payment methods, owner and hours in onboarding order', function () {
        $order = [];
        $record = function (string $step) use (&$order): bool {
            $order[] = $step;

            return true;
        };

        $this->businesses->shouldReceive('save')->once()
            ->with(Mockery::on(fn (): bool => $record('save')));
        $this->roles->shouldReceive('provisionFor')->once()
            ->with(Mockery::on(fn (): bool => $record('roles')));
        $this->paymentMethods->shouldReceive('provisionFor')->once()
            ->with(Mockery::on(fn (): bool => $record('payment methods')));
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(Mockery::on(fn (): bool => $record('owner')), Mockery::any())
            ->andReturn(OnboardingFixtures::ownerRegistration());
        $this->schedules->shouldReceive('provisionDefaultsFor')->once()
            ->with(Mockery::on(fn (): bool => $record('default hours')), Mockery::any());

        $this->useCase->handle($this->input);

        expect($order)->toBe(['save', 'roles', 'payment methods', 'owner', 'default hours']);
    });

    it('writes the business and everything it reprovisions inside one transaction', function () {
        $this->businesses->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->roles->shouldReceive('provisionFor')->once()->with(Mockery::on($this->recordTransactionState));
        $this->paymentMethods->shouldReceive('provisionFor')->once()->with(Mockery::on($this->recordTransactionState));
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(Mockery::on($this->recordTransactionState), Mockery::any())
            ->andReturn(OnboardingFixtures::ownerRegistration());
        $this->schedules->shouldReceive('provisionDefaultsFor')->once()
            ->with(Mockery::on($this->recordTransactionState), Mockery::any());

        $this->useCase->handle($this->input);

        expect($this->insideTransaction)->toBe([true, true, true, true, true])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('announces what the owner registration earned, in order, once the transaction has closed', function () {
        $this->businesses->shouldReceive('save')->once();
        $this->roles->shouldReceive('provisionFor')->once();
        $this->paymentMethods->shouldReceive('provisionFor')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->andReturn(OnboardingFixtures::ownerRegistration($this->ownerEvents));
        $this->schedules->shouldReceive('provisionDefaultsFor')->once();

        $announced = [];
        $this->events->shouldReceive('dispatch')->twice()
            ->with(Mockery::on(function (object $event) use (&$announced): bool {
                $announced[] = [$event, $this->transactions->isRunning()];

                return true;
            }));

        $this->useCase->handle($this->input);

        expect($announced)->toBe([
            [$this->ownerEvents[0], false],
            [$this->ownerEvents[1], false],
        ]);
    });

    it('answers with the refusal when the owner already runs another business, announcing nothing', function () {
        $this->businesses->shouldReceive('save')->once();
        $this->roles->shouldReceive('provisionFor')->once();
        $this->paymentMethods->shouldReceive('provisionFor')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->andThrow(OwnerAlreadyHasBusiness::forAccount(OnboardingFixtures::OWNER_ACCOUNT_ID));
        $this->schedules->shouldNotReceive('provisionDefaultsFor');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle($this->input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('owner_already_has_business')
            ->and($this->transactions->runs())->toBe(1);
    });

    it('announces nothing when the commit itself fails', function () {
        $this->businesses->shouldReceive('save')->once();
        $this->roles->shouldReceive('provisionFor')->once();
        $this->paymentMethods->shouldReceive('provisionFor')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->andReturn(OnboardingFixtures::ownerRegistration($this->ownerEvents));
        $this->schedules->shouldReceive('provisionDefaultsFor')->once();
        $this->events->shouldNotReceive('dispatch');

        $commitFailure = new RuntimeException('the connection went away before COMMIT');
        $this->transactions->failAtCommit($commitFailure);

        expect(fn () => $this->useCase->handle($this->input))->toThrow($commitFailure);
    });
});

describe('refusing to reopen', function () {
    beforeEach(function () {
        $this->businesses->shouldNotReceive('save');
        ($this->expectNoReprovisioning)();
        $this->events->shouldNotReceive('dispatch');
    });

    it('answers a conflict for a business that is not closed', function () {
        $this->businesses->shouldReceive('findClosedById')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID)->andReturnNull();

        $response = $this->useCase->handle($this->input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause()->getMessage())
            ->toBe('Business ['.OnboardingFixtures::GENERATED_BUSINESS_ID.'] is not closed.')
            ->and($this->transactions->runs())->toBe(0);
    });

    it('answers a conflict for an account other than the one that closed it, leaving it closed', function (string $state) {
        $business = $state === 'purged' ? ClosureFixtures::purgedBusiness() : ClosureFixtures::closedBusiness();
        $this->businesses->shouldReceive('findClosedById')->once()->andReturn($business);

        $response = $this->useCase->handle(new ReopenBusinessInput(
            businessId: OnboardingFixtures::GENERATED_BUSINESS_ID,
            ownerAccountId: ClosureFixtures::STRANGER_ACCOUNT_ID,
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($business->isClosed())->toBeTrue()
            ->and($this->transactions->runs())->toBe(0);
    })->with(['in grace' => 'closed', 'after the purge' => 'purged']);
});
