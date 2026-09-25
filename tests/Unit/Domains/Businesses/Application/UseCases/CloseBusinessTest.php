<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CloseBusinessInput;
use App\Domains\Businesses\Application\UseCases\CloseBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\TeamSignOut;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\InvalidBusinessOwner;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\ClosureFixtures;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->teamSignOut = Mockery::mock(TeamSignOut::class);
    $this->transactions = new FakeTransactionManager;

    $this->useCase = new CloseBusiness(
        $this->businesses,
        $this->teamSignOut,
        new FakeClock(ClosureFixtures::closedAt()),
        $this->transactions,
    );

    $this->input = new CloseBusinessInput(
        businessId: OnboardingFixtures::GENERATED_BUSINESS_ID,
        ownerAccountId: OnboardingFixtures::OWNER_ACCOUNT_ID,
    );

    $this->openBusiness = OnboardingFixtures::business();

    $this->insideTransaction = [];
    $this->recordTransactionState = function (): bool {
        $this->insideTransaction[] = $this->transactions->isRunning();

        return true;
    };
});

describe('closing an open business', function () {
    beforeEach(function () {
        $this->businesses->shouldReceive('findById')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID)->andReturn($this->openBusiness);
    });

    it('saves the business closed at the clock instant by the owner who asked', function () {
        $saved = null;
        $this->teamSignOut->shouldReceive('signOutTeamOf')->once();
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $response = $this->useCase->handle($this->input);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull()
            ->and($saved)->toBe($this->openBusiness)
            ->and($saved->isClosed())->toBeTrue()
            ->and($saved->closedAt())->toEqual(ClosureFixtures::closedAt())
            ->and($saved->closedByAccountId())->toBe(OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->and($saved->purgeScheduledAt())->toEqual(ClosureFixtures::purgeDueAt())
            ->and($saved->isPurged())->toBeFalse();
    });

    it('signs out the team of that business, exactly once', function () {
        $this->teamSignOut->shouldReceive('signOutTeamOf')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->businesses->shouldReceive('save')->once();

        $this->useCase->handle($this->input);
    });

    it('signs the team out before it saves the closure', function () {
        $order = [];
        $record = function (string $step) use (&$order): bool {
            $order[] = $step;

            return true;
        };

        $this->teamSignOut->shouldReceive('signOutTeamOf')->once()
            ->with(Mockery::on(fn (): bool => $record('sign out')));
        $this->businesses->shouldReceive('save')->once()
            ->with(Mockery::on(fn (): bool => $record('save')));

        $this->useCase->handle($this->input);

        expect($order)->toBe(['sign out', 'save']);
    });

    it('signs out and saves inside one transaction', function () {
        $this->teamSignOut->shouldReceive('signOutTeamOf')->once()->with(Mockery::on($this->recordTransactionState));
        $this->businesses->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));

        $this->useCase->handle($this->input);

        expect($this->insideTransaction)->toBe([true, true])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('records the account from the input as the one that closed it', function () {
        $saved = null;
        $this->teamSignOut->shouldReceive('signOutTeamOf')->once();
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $this->useCase->handle(new CloseBusinessInput(
            businessId: OnboardingFixtures::GENERATED_BUSINESS_ID,
            ownerAccountId: ClosureFixtures::STRANGER_ACCOUNT_ID,
        ));

        expect($saved->closedByAccountId())->toBe(ClosureFixtures::STRANGER_ACCOUNT_ID);
    });

    it('answers with the refusal the save raised for a closing account storage does not know', function () {
        $refusal = InvalidBusinessOwner::unknownAccount(OnboardingFixtures::OWNER_ACCOUNT_ID);
        $this->teamSignOut->shouldReceive('signOutTeamOf')->once();
        $this->businesses->shouldReceive('save')->once()->andThrow($refusal);

        $response = $this->useCase->handle($this->input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->cause())->toBe($refusal)
            ->and($this->transactions->runs())->toBe(1);
    });

    it('lets a sign-out failure escape and never saves the closure', function () {
        $outage = new RuntimeException('the session store went away');
        $this->teamSignOut->shouldReceive('signOutTeamOf')->once()->andThrow($outage);
        $this->businesses->shouldNotReceive('save');

        expect(fn () => $this->useCase->handle($this->input))->toThrow($outage);
    });
});

describe('refusing to close', function () {
    beforeEach(function () {
        $this->teamSignOut->shouldNotReceive('signOutTeamOf');
        $this->businesses->shouldNotReceive('save');
    });

    it('answers not found for a business the repository does not have', function () {
        $this->businesses->shouldReceive('findById')->once()
            ->andThrow(BusinessNotFound::withId(OnboardingFixtures::GENERATED_BUSINESS_ID));

        $response = $this->useCase->handle($this->input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->transactions->runs())->toBe(0);
    });

    it('answers a conflict for a business that is already closed, keeping the first closure', function () {
        $closed = ClosureFixtures::closedBusiness(closedAt: '2026-01-15T08:00:00+00:00');
        $this->businesses->shouldReceive('findById')->once()->andReturn($closed);

        $response = $this->useCase->handle($this->input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_already_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($closed->closedAt())->toEqual(new DateTimeImmutable('2026-01-15T08:00:00+00:00'))
            ->and($this->transactions->runs())->toBe(0);
    });

    it('answers a conflict for a business already purged, since it is still closed', function () {
        $this->businesses->shouldReceive('findById')->once()->andReturn(ClosureFixtures::purgedBusiness());

        expect($this->useCase->handle($this->input)->error()->code)->toBe('business_already_closed');
    });
});
